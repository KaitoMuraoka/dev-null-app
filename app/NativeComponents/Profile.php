<?php

namespace App\NativeComponents;

use App\Devnull\Data\Commit;
use App\Devnull\Data\Profile as ProfileData;
use App\Devnull\Timeline;
use App\Icons\Lucide;
use App\NativeComponents\Concerns\ExpandsThreads;
use App\NativeComponents\Concerns\HoldsNewCommits;
use App\NativeComponents\Concerns\OpensInTab;
use App\NativeComponents\Concerns\RefreshesInBackground;
use App\NativeComponents\Concerns\ShowsCommits;
use App\NativeComponents\Concerns\TalksToDevnull;
use Closure;
use Illuminate\View\View;
use Native\Mobile\Edge\Layouts\Builders\NavAction;
use Native\Mobile\Edge\Layouts\Builders\NavBarOptions;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Facades\Browser;

/**
 * A developer's profile: who they are on GitHub, their counts and activity,
 * then their commits (with the pinned one on top) or their replies.
 *
 * The Profile tab shows your own, with no username in the route. Like the web,
 * your own profile swaps Follow for "Edit on GitHub", and its nav bar leads to
 * this device's key.
 *
 * It opens on the profile as it was last time, then asks devnull again behind
 * it. The header changes in place; newer commits wait in the pill (see
 * HoldsNewCommits).
 */
class Profile extends NativeComponent
{
    use ExpandsThreads;
    use HoldsNewCommits;
    use OpensInTab {
        isShowing as isShowingPath;
    }
    use RefreshesInBackground;
    use ShowsCommits;
    use TalksToDevnull;

    public string $username = '';

    public const array Tabs = ['commits', 'replies'];

    public ?ProfileData $profile = null;

    public string $tab = 'commits';

    /**
     * Whether the tab has had commits to show, kept or fresh. Until then it shows skeleton rows.
     */
    public bool $commitsLoaded = false;

    public function mount(): void
    {
        if ($this->username === '') {
            $this->username = $this->me()?->username ?? '';
        }

        $this->showKept();
        $this->revalidate();
    }

    /**
     * Back from a commit or another profile: take any change made there from the cache at once, then ask devnull.
     */
    public function onResume(): void
    {
        $this->forgetRefreshes();
        $this->showKept();
        $this->revalidate();
    }

    public function navTitle(): string
    {
        return '@'.$this->username;
    }

    public function navigationOptions(): ?NavBarOptions
    {
        if (! $this->isMe()) {
            return null;
        }

        return NavBarOptions::make()->action(
            NavAction::make('device')
                ->icon(ios: Lucide::Settings2->ios(), android: Lucide::Settings2->android())
                ->a11yLabel('This device')
                ->press('openDevice'),
        );
    }

    /**
     * Pull to refresh. You're at the top, so new commits go straight in.
     */
    public function load(): void
    {
        $this->refreshProfile(showNewer: true);
    }

    public function showTab(string $tab): void
    {
        if (! in_array($tab, self::Tabs, true) || $tab === $this->tab) {
            return;
        }

        $this->tab = $tab;
        $this->commits = [];
        $this->threads = [];
        $this->nextCursor = null;
        $this->commitsLoaded = false;
        $this->forgetNewCommits();

        if (($kept = $this->cached($this->commitsPath(), $this->commitsQuery())) !== null) {
            $this->takeCommits($tab, $kept);
        }

        $this->refreshCommits();
    }

    public function loadMore(): void
    {
        if ($this->nextCursor === null) {
            return;
        }

        $page = $this->attempt(fn ($devnull) => $devnull->userCommits($this->username, $this->tab, $this->nextCursor));

        if ($page !== null) {
            $this->commits = [...$this->commits, ...$page->items];
            $this->nextCursor = $page->nextCursor;
        }
    }

    /**
     * Follows or unfollows straight away, and puts it back if the server says no.
     */
    public function toggleFollow(): void
    {
        if ($this->profile === null || $this->isMe()) {
            return;
        }

        $before = $this->profile;
        $this->profile = $before->withFollowing(! $before->isFollowing);

        $done = $this->attempt(function ($devnull) use ($before): bool {
            $before->isFollowing
                ? $devnull->unfollow($this->username)
                : $devnull->follow($this->username);

            return true;
        });

        if ($done !== true) {
            $this->profile = $before;
        }
    }

    public function isMe(): bool
    {
        return $this->username !== '' && $this->me()?->username === $this->username;
    }

    /**
     * The Profile tab shows your own profile at /profile, so your own profile
     * under the tab counts as this screen too.
     */
    protected function isShowing(string $uri): bool
    {
        return $this->isShowingPath($uri)
            || ($this->isShowingPath($this->tabRoot()) && '/'.trim($uri, '/') === $this->tabRoot().'/users/'.$this->username);
    }

    public function openDevice(): void
    {
        $this->navigate('/profile/device');
    }

    public function editOnGitHub(): void
    {
        Browser::inApp('https://github.com/settings/profile');
    }

    /**
     * The website on their GitHub profile. Taps name the link rather than carry
     * a URL, so the URL always comes from what devnull sent.
     */
    public function openWebsite(): void
    {
        $url = $this->profile?->user->websiteUrl();

        if ($url !== null && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            Browser::inApp($url);
        }
    }

    public function openGitHubProfile(): void
    {
        if ($this->profile !== null) {
            Browser::inApp($this->profile->user->gitHubProfileUrl());
        }
    }

    /**
     * The web leaves the pinned commit out of the replies tab.
     */
    public function pinnedCommit(): ?Commit
    {
        return $this->tab === 'commits' ? $this->profile?->pinnedCommit : null;
    }

    /**
     * @return iterable<Commit>
     */
    protected function shownCommits(): iterable
    {
        return [...($this->pinnedCommit() === null ? [] : [$this->pinnedCommit()]), ...$this->commits, ...$this->threadCommits()];
    }

    protected function updateShownCommits(Closure $update): void
    {
        $this->commits = array_map($update, $this->commits);
        $this->threads = array_map(fn (array $replies): array => array_map($update, $replies), $this->threads);

        if ($this->profile?->pinnedCommit !== null) {
            $this->profile = $this->profile->withPinnedCommit($update($this->profile->pinnedCommit));
        }
    }

    /**
     * Asks again for the profile and the tab, and on your own the unread count,
     * which picks up name and avatar changes too.
     */
    protected function revalidate(): void
    {
        $this->refreshProfile();
    }

    protected function showsContent(): bool
    {
        return $this->profile !== null;
    }

    private function refreshProfile(bool $showNewer = false): void
    {
        if ($this->username === '') {
            return;
        }

        $this->refresh('users/'.rawurlencode($this->username), [], function (array $json): void {
            $this->profile = ProfileData::fromArray($json);
        }, again: $showNewer);
        $this->refreshCommits($showNewer);
        $this->refreshThreads();

        if ($this->isMe()) {
            $this->refreshUnreadNotifications();
        }
    }

    private function refreshCommits(bool $showNewer = false): void
    {
        $tab = $this->tab;

        $this->refresh($this->commitsPath(), $this->commitsQuery(), fn (array $json) => $this->takeCommits($tab, $json, $showNewer), again: $showNewer);
    }

    /**
     * The profile and the tab as they were kept last time.
     */
    private function showKept(): void
    {
        if ($this->username === '') {
            return;
        }

        $profile = $this->cached('users/'.rawurlencode($this->username));

        if ($profile !== null) {
            $this->profile = ProfileData::fromArray($profile);
        }

        if (($commits = $this->cached($this->commitsPath(), $this->commitsQuery())) !== null) {
            $this->takeCommits($this->tab, $commits);
        }
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function takeCommits(string $tab, array $json, bool $showNewer = false): void
    {
        // An answer for the other tab, which you've left.
        if ($tab !== $this->tab) {
            return;
        }

        $this->layFreshCommits($json, $showNewer || ! $this->commitsLoaded);
        $this->commitsLoaded = true;
    }

    private function commitsPath(): string
    {
        return 'users/'.rawurlencode($this->username).'/commits';
    }

    /**
     * @return array<string, string|null>
     */
    private function commitsQuery(): array
    {
        return ['replies' => $this->tab === 'replies' ? '1' : null];
    }

    public function render(): View
    {
        $pinnedCommit = $this->pinnedCommit();

        return view('native.profile', [
            'isMe' => $this->isMe(),
            'newCommitsLabel' => $this->newCommitsLabel(),
            'viewer' => $this->me(),
            'pinnedCommit' => $pinnedCommit,
            'rows' => Timeline::feed($this->commits, firstLane: $pinnedCommit?->lane()),
        ]);
    }
}
