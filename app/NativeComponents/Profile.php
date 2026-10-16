<?php

namespace App\NativeComponents;

use App\Devnull\Data\Commit;
use App\Devnull\Data\Profile as ProfileData;
use App\Devnull\Timeline;
use App\Icons\Lucide;
use App\NativeComponents\Concerns\ExpandsThreads;
use App\NativeComponents\Concerns\OpensInTab;
use App\NativeComponents\Concerns\ShowsCommits;
use App\NativeComponents\Concerns\TalksToDevnull;
use Closure;
use Illuminate\View\View;
use Native\Mobile\Attributes\Lazy;
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
 */
#[Lazy]
class Profile extends NativeComponent
{
    use ExpandsThreads;
    use OpensInTab {
        isShowing as isShowingPath;
    }
    use ShowsCommits;
    use TalksToDevnull;

    public string $username = '';

    public const array Tabs = ['commits', 'replies'];

    public ?ProfileData $profile = null;

    public string $tab = 'commits';

    /** @var list<Commit> */
    public array $commits = [];

    public ?string $nextCursor = null;

    public function mount(): void
    {
        if ($this->username === '') {
            $this->username = $this->me()?->username ?? '';
        }

        $this->load();
    }

    public function onResume(): void
    {
        $this->load();
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
     * Loads the profile, and on your own picks up name and avatar changes and the
     * unread count, which the Me tab used to fetch.
     */
    public function load(): void
    {
        if ($this->username === '') {
            return;
        }

        $profile = $this->attempt(fn ($devnull) => $devnull->profile($this->username));

        if ($profile === null) {
            return;
        }

        $this->profile = $profile;
        $this->loadCommits();
        $this->reloadThreads();

        if ($this->isMe()) {
            $this->refreshUnreadNotifications();
        }
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
        $this->loadCommits();
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

    public function openCommit(string $id): void
    {
        $this->openInTab('/commits/'.$id);
    }

    public function openProfile(string $username): void
    {
        $this->openInTab('/users/'.$username);
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

    private function loadCommits(): void
    {
        $page = $this->attempt(fn ($devnull) => $devnull->userCommits($this->username, $this->tab));

        if ($page !== null) {
            $this->commits = $page->items;
            $this->nextCursor = $page->nextCursor;
        }
    }

    public function render(): View
    {
        $pinnedCommit = $this->pinnedCommit();

        return view('native.profile', [
            'isMe' => $this->isMe(),
            'viewer' => $this->me(),
            'pinnedCommit' => $pinnedCommit,
            'rows' => Timeline::feed($this->commits, firstLane: $pinnedCommit?->lane()),
        ]);
    }
}
