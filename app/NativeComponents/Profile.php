<?php

namespace App\NativeComponents;

use App\Devnull\Data\Commit;
use App\Devnull\Data\Profile as ProfileData;
use App\NativeComponents\Concerns\ShowsCommits;
use App\NativeComponents\Concerns\TalksToDevnull;
use Illuminate\View\View;
use Native\Mobile\Attributes\Lazy;
use Native\Mobile\Edge\NativeComponent;

/**
 * A developer's profile: who they are on GitHub, their counts, and their commits.
 */
#[Lazy]
class Profile extends NativeComponent
{
    use ShowsCommits;
    use TalksToDevnull;

    public string $username = '';

    public ?ProfileData $profile = null;

    /** @var list<Commit> */
    public array $commits = [];

    public ?string $nextCursor = null;

    public function mount(): void
    {
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

    public function load(): void
    {
        $profile = $this->attempt(fn ($devnull) => $devnull->profile($this->username));

        if ($profile === null) {
            return;
        }

        $this->profile = $profile;

        $page = $this->attempt(fn ($devnull) => $devnull->userCommits($this->username));

        if ($page !== null) {
            $this->commits = $page->items;
            $this->nextCursor = $page->nextCursor;
        }
    }

    public function loadMore(): void
    {
        if ($this->nextCursor === null) {
            return;
        }

        $page = $this->attempt(fn ($devnull) => $devnull->userCommits($this->username, $this->nextCursor));

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
        return $this->me()?->username === $this->username;
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
     * @return iterable<Commit>
     */
    protected function shownCommits(): iterable
    {
        return $this->commits;
    }

    public function render(): View
    {
        return view('native.profile', ['isMe' => $this->isMe()]);
    }
}
