<?php

namespace App\NativeComponents;

use App\Devnull\Data\Notification;
use App\NativeComponents\Concerns\TalksToDevnull;
use Illuminate\View\View;
use Native\Mobile\Attributes\Lazy;
use Native\Mobile\Edge\NativeComponent;

/**
 * Follows, replies, mentions, and moderation updates for your commits.
 */
#[Lazy]
class Notifications extends NativeComponent
{
    use TalksToDevnull;

    /** @var list<Notification> */
    public array $notifications = [];

    public ?string $nextCursor = null;

    public bool $loaded = false;

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
        return 'Notifications';
    }

    public function load(): void
    {
        $page = $this->attempt(fn ($devnull) => $devnull->notifications());

        if ($page !== null) {
            $this->notifications = $page->items;
            $this->nextCursor = $page->nextCursor;
        }

        $this->loaded = true;
    }

    public function loadMore(): void
    {
        if ($this->nextCursor === null) {
            return;
        }

        $page = $this->attempt(fn ($devnull) => $devnull->notifications($this->nextCursor));

        if ($page !== null) {
            $this->notifications = [...$this->notifications, ...$page->items];
            $this->nextCursor = $page->nextCursor;
        }
    }

    public function open(string $id): void
    {
        foreach ($this->notifications as $notification) {
            if ($notification->id !== $id) {
                continue;
            }

            $this->navigate($notification->opensCommit()
                ? '/commits/'.$notification->commitId
                : '/users/'.$notification->actor->username);

            return;
        }
    }

    public function render(): View
    {
        return view('native.notifications');
    }
}
