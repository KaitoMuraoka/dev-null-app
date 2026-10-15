<?php

namespace App\NativeComponents;

use App\Devnull\Data\Commit;
use App\Devnull\Lane;
use App\Devnull\LastVisit;
use App\Devnull\Timeline;
use App\Icons\Android;
use App\Icons\Ios;
use App\NativeComponents\Concerns\OpensInTab;
use App\NativeComponents\Concerns\ShowsCommits;
use App\NativeComponents\Concerns\TalksToDevnull;
use Illuminate\View\View;
use Native\Mobile\Attributes\Lazy;
use Native\Mobile\Edge\Layouts\Builders\NavAction;
use Native\Mobile\Edge\Layouts\Builders\NavBarOptions;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Edge\Transition;

/**
 * The log: commits from you and the people you follow, or from everyone.
 */
#[Lazy]
class Log extends NativeComponent
{
    use OpensInTab;
    use ShowsCommits;
    use TalksToDevnull;

    public const array Logs = ['following', 'everyone'];

    public string $log = 'following';

    /** @var list<Commit> */
    public array $commits = [];

    public ?string $nextCursor = null;

    public bool $loaded = false;

    /**
     * The newest commit time you'd been shown in this log when the screen opened.
     * The last-visit marker stays put against it while the screen is open, as on the web.
     */
    public ?string $lastVisitAt = null;

    public function mount(): void
    {
        $this->lastVisitAt = $this->lastVisitSeenAt();
        $this->load();
    }

    public function onResume(): void
    {
        $this->load();
    }

    public function navTitle(): string
    {
        return $this->log === 'following' ? 'Log' : 'Everyone';
    }

    public function navigationOptions(): ?NavBarOptions
    {
        return NavBarOptions::make()->action(
            NavAction::make('compose')
                ->icon(ios: Ios::SquareAndPencil, android: Android::Edit)
                ->a11yLabel('New commit')
                ->press('compose'),
        );
    }

    public function show(string $log): void
    {
        if (! in_array($log, self::Logs, true)) {
            return;
        }

        $this->log = $log;
        $this->commits = [];
        $this->nextCursor = null;
        $this->lastVisitAt = $this->lastVisitSeenAt();
        $this->load();
    }

    /**
     * Loads the log, remembers its newest commit as seen, then fetches the unread
     * count for the Notifications badge.
     */
    public function load(): void
    {
        $page = $this->attempt(fn ($devnull) => $devnull->log($this->log));

        if ($page !== null) {
            $this->commits = $page->items;
            $this->nextCursor = $page->nextCursor;

            if (($viewer = $this->me()) !== null) {
                app(LastVisit::class)->remember($viewer->id, $this->log, $this->commits);
            }

            $this->refreshUnreadNotifications();
        }

        $this->loaded = true;
    }

    public function loadMore(): void
    {
        if ($this->nextCursor === null) {
            return;
        }

        $page = $this->attempt(fn ($devnull) => $devnull->log($this->log, $this->nextCursor));

        if ($page !== null) {
            $this->commits = [...$this->commits, ...$page->items];
            $this->nextCursor = $page->nextCursor;
        }
    }

    public function compose(): void
    {
        $this->navigate('/compose')->transition(Transition::SlideFromBottom);
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
     * @return iterable<Commit>
     */
    protected function shownCommits(): iterable
    {
        return $this->commits;
    }

    private function lastVisitSeenAt(): ?string
    {
        $viewer = $this->me();

        return $viewer === null ? null : app(LastVisit::class)->seenAt($viewer->id, $this->log);
    }

    public function render(): View
    {
        $viewer = $this->me();

        return view('native.log', [
            'viewer' => $viewer,
            'rows' => Timeline::feed(
                $this->commits,
                firstLane: Lane::Rail,
                firstSeenId: LastVisit::firstSeenId($this->commits, $this->lastVisitAt, $viewer?->id),
            ),
        ]);
    }
}
