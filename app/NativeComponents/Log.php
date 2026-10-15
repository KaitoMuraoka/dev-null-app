<?php

namespace App\NativeComponents;

use App\Devnull\Data\Commit;
use App\Devnull\Lane;
use App\Devnull\LastVisit;
use App\Devnull\Timeline;
use App\Icons\Android;
use App\Icons\Ios;
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
use Native\Mobile\Edge\Transition;
use Native\Mobile\Facades\Dialog;

/**
 * The log: commits from you and the people you follow, or from everyone, under
 * a composer you type into where it is, as on the web.
 *
 * EDGE tells PHP nothing about focus, so a touch on the field is taken as
 * being in it.
 */
#[Lazy]
class Log extends NativeComponent
{
    use ExpandsThreads;
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

    /**
     * The commit you're writing. It stays when the keyboard goes, until it's committed.
     */
    public string $body = '';

    /**
     * Whether the composer shows its budget and Commit button: from the moment you're
     * in it until you commit, or leave it empty and the log reloads.
     */
    public bool $composing = false;

    /**
     * Mounts a fresh field when it changes, so a committed field starts clean.
     */
    public int $composerKey = 0;

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
        $this->threads = [];
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
        $this->closeEmptyComposer();

        $page = $this->attempt(fn ($devnull) => $devnull->log($this->log));

        if ($page !== null) {
            $this->commits = $page->items;
            $this->nextCursor = $page->nextCursor;

            if (($viewer = $this->me()) !== null) {
                app(LastVisit::class)->remember($viewer->id, $this->log, $this->commits);
            }

            $this->reloadThreads();
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

    /**
     * A touch on the composer's field puts you in it.
     */
    public function composerFocused(): void
    {
        $this->composing = true;
    }

    public function remaining(): int
    {
        return Commit::MaxLength - mb_strlen($this->body);
    }

    public function canCommit(): bool
    {
        $length = mb_strlen(trim($this->body));

        return $length >= Commit::MinLength && $length <= Commit::MaxLength;
    }

    /**
     * Commits the draft and puts it at the top of the log. Pressing Commit has
     * already put the keyboard away. The draft stays if devnull says no.
     */
    public function commit(): void
    {
        if (! $this->canCommit()) {
            return;
        }

        $commit = $this->attempt(fn ($devnull) => $devnull->createCommit(trim($this->body)));

        if ($commit === null) {
            return;
        }

        $this->body = '';
        $this->composing = false;
        $this->composerKey++;
        $this->commits = [$commit, ...$this->commits];

        Dialog::toast($commit->isPending()
            ? "[log {$commit->shortHash()}] pending: only you can see it until it's approved."
            : "[log {$commit->shortHash()}] {$commit->body}");
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
        return [...$this->commits, ...$this->threadCommits()];
    }

    protected function updateShownCommits(Closure $update): void
    {
        $this->commits = array_map($update, $this->commits);
        $this->threads = array_map(fn (array $replies): array => array_map($update, $replies), $this->threads);
    }

    /**
     * An empty composer you've left closes again. Nothing says when you leave it,
     * so this waits for the log to reload: on returning to it, or pulling to refresh.
     */
    private function closeEmptyComposer(): void
    {
        if (! $this->composing || $this->body !== '') {
            return;
        }

        $this->composing = false;
        $this->composerKey++;
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
            'composerOpen' => $this->composing || $this->body !== '',
            'remaining' => $this->remaining(),
            'canCommit' => $this->canCommit(),
            'rows' => Timeline::feed(
                $this->commits,
                firstLane: Lane::Rail,
                firstSeenId: LastVisit::firstSeenId($this->commits, $this->lastVisitAt, $viewer?->id),
            ),
        ]);
    }
}
