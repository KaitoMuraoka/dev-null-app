<?php

namespace App\NativeComponents;

use App\Devnull\Data\Notification;
use App\Devnull\Data\Page;
use App\NativeComponents\Concerns\OpensInTab;
use App\NativeComponents\Concerns\RefreshesInBackground;
use App\NativeComponents\Concerns\TalksToDevnull;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

/**
 * Follows, stars, replies, mentions, and moderation updates for your commits,
 * grouped as the web groups them.
 *
 * It opens on the notifications as they were last time, all read by then,
 * then asks devnull behind them. New ones go straight in at the top, quietly:
 * a group's id moves on as people join it, so there's no telling a new group
 * from a busier one, and the tab's badge has already said they're coming.
 */
class Notifications extends NativeComponent
{
    use OpensInTab;
    use RefreshesInBackground;
    use TalksToDevnull;

    /** @var list<Notification> */
    public array $notifications = [];

    public ?string $nextCursor = null;

    public bool $loaded = false;

    public function mount(): void
    {
        $this->showKept();
        $this->revalidate();
    }

    public function onResume(): void
    {
        $this->forgetRefreshes();
        $this->revalidate();
    }

    public function navTitle(): string
    {
        return 'Notifications';
    }

    /**
     * Pull to refresh.
     */
    public function load(): void
    {
        $this->refresh('notifications', [], fn (array $json) => $this->takeFresh($json), again: true);
    }

    /**
     * devnull marks a page read as it sends it, so the badge is asked for
     * again once it lands, which clears it unless more are waiting further down.
     */
    protected function revalidate(): void
    {
        $this->refresh('notifications', [], fn (array $json) => $this->takeFresh($json));
    }

    protected function showsContent(): bool
    {
        return $this->loaded;
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
            $this->refreshUnreadNotifications();
        }
    }

    public function open(string $id): void
    {
        foreach ($this->notifications as $notification) {
            if ($notification->id !== $id) {
                continue;
            }

            $commitId = $notification->commitToOpen();

            $this->openInTab($commitId !== null
                ? '/commits/'.$commitId
                : '/users/'.$notification->actor->username);

            return;
        }
    }

    private function showKept(): void
    {
        if (($json = $this->cached('notifications')) === null) {
            return;
        }

        $page = Page::fromArray($json, Notification::fromArray(...));

        $this->notifications = $page->items;
        $this->nextCursor = $page->nextCursor;
        $this->loaded = true;
    }

    /**
     * The fresh first page replaces what's showing. Groups loaded further down
     * stay, past the last one the fresh page also has.
     *
     * @param  array<string, mixed>  $json
     */
    private function takeFresh(array $json): void
    {
        $page = Page::fromArray($json, Notification::fromArray(...));
        $fresh = array_flip(array_map(fn (Notification $group): string => $group->id, $page->items));

        $deepest = -1;

        foreach ($this->notifications as $index => $group) {
            if (isset($fresh[$group->id])) {
                $deepest = $index;
            }
        }

        $later = $deepest < 0 ? [] : array_values(array_filter(
            array_slice($this->notifications, $deepest + 1),
            fn (Notification $group): bool => ! isset($fresh[$group->id]),
        ));

        $this->notifications = [...$page->items, ...$later];
        $this->nextCursor = $later === [] ? $page->nextCursor : $this->nextCursor;
        $this->loaded = true;
        $this->refreshUnreadNotifications();
    }

    public function render(): View
    {
        return view('native.notifications');
    }
}
