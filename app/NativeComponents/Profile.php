<?php

namespace App\NativeComponents;

use App\Devnull\Data\Commit;
use App\Devnull\Data\Profile as ProfileData;
use App\Devnull\Timeline;
use App\Icons\Lucide;
use App\NativeComponents\Concerns\ShowsCommits;
use App\NativeComponents\Concerns\TalksToDevnull;
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

    public function openDevice(): void
    {
        $this->navigate('/device');
    }

    public function editOnGitHub(): void
    {
        Browser::inApp('https://github.com/settings/profile');
    }

    public function openCommit(string $id): void
    {
        $this->navigate('/commits/'.$id);
    }

    public function openProfile(string $username): void
    {
        if ($username !== $this->username) {
            $this->navigate('/users/'.$username);
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
        return [...($this->pinnedCommit() === null ? [] : [$this->pinnedCommit()]), ...$this->commits];
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
