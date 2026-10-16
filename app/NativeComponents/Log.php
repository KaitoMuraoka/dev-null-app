<?php

namespace App\NativeComponents;

use App\Devnull\Data\Commit;
use App\Devnull\Lane;
use App\Devnull\LastVisit;
use App\Devnull\Timeline;
use App\Icons\Android;
use App\Icons\Ios;
use App\NativeComponents\Concerns\ExpandsThreads;
use App\NativeComponents\Concerns\HoldsNewCommits;
use App\NativeComponents\Concerns\OpensInTab;
use App\NativeComponents\Concerns\RefreshesInBackground;
use App\NativeComponents\Concerns\ShowsCommits;
use App\NativeComponents\Concerns\TalksToDevnull;
use Closure;
use Illuminate\View\View;
use Native\Mobile\Attributes\Poll;
use Native\Mobile\Edge\Layouts\Builders\NavAction;
use Native\Mobile\Edge\Layouts\Builders\NavBarOptions;
use Native\Mobile\Edge\NativeComponent;

/**
 * The log: commits from you and the people you follow, or from everyone, under
 * a composer you type into where it is, as on the web.
 *
 * EDGE tells PHP nothing about focus, and gives PHP no way to focus a field
 * or scroll a list. So a touch on the field is taken as being in it, a fresh
 * field (a new key) with autofocus is how focus is given, and a new key on the
 * list is how it gets back to the top.
 *
 * It opens on the log as it was last time, then asks devnull again in the
 * background, and every 30 seconds while it's showing, as the web polls.
 * Commits newer than anything showing wait under the tabs in the new commits
 * pill (see HoldsNewCommits).
 */
class Log extends NativeComponent
{
    use ExpandsThreads;
    use HoldsNewCommits;
    use OpensInTab;
    use RefreshesInBackground;
    use ShowsCommits;
    use TalksToDevnull;

    public const array Logs = ['following', 'everyone'];

    public string $log = 'following';

    /**
     * Whether the log has had commits to show, kept or fresh. Until then it shows skeleton rows.
     */
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

    /**
     * Asks the next fresh field to take the focus, until you touch it or type.
     */
    public bool $focusComposer = false;

    public function mount(): void
    {
        $this->lastVisitAt = $this->lastVisitSeenAt();
        $this->showKept();
        $this->revalidate();
    }

    /**
     * Back from a commit or a profile: take any change made there from the cache at once, then ask devnull.
     */
    public function onResume(): void
    {
        $this->forgetRefreshes();
        $this->closeEmptyComposer();
        $this->showKept();
        $this->revalidate();
    }

    /**
     * The web's poll for new commits.
     */
    #[Poll(30000)]
    public function checkForNewCommits(): void
    {
        $this->revalidate();
    }

    /**
     * The bar shows the logo instead (see TabsLayout). This names it for VoiceOver and the back menu.
     */
    public function navTitle(): string
    {
        return 'devnull';
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
        $this->loaded = false;
        $this->error = null;
        $this->forgetNewCommits();
        $this->lastVisitAt = $this->lastVisitSeenAt();
        $this->showKept();
        $this->revalidate();
    }

    /**
     * Pull to refresh. You're at the top, so anything new goes straight in.
     */
    public function load(): void
    {
        $this->closeEmptyComposer();

        $log = $this->log;
        $this->refresh('log/'.$log, [], fn (array $json) => $this->takeFresh($log, $json, showNewer: true), again: true);
        $this->refreshThreads();
        $this->refreshUnreadNotifications();
    }

    /**
     * The pill: the new commits go on top, and they're the newest you've seen now.
     */
    public function showNewCommits(): void
    {
        if ($this->newCommits === []) {
            return;
        }

        $this->putNewCommitsOnTop();
        $this->listKey++;
        $this->rememberLastVisit();
    }

    protected function revalidate(): void
    {
        $log = $this->log;
        $this->refresh('log/'.$log, [], fn (array $json) => $this->takeFresh($log, $json));
        $this->refreshThreads();
        $this->refreshUnreadNotifications();
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

        $page = $this->attempt(fn ($devnull) => $devnull->log($this->log, $this->nextCursor));

        if ($page !== null) {
            $this->commits = [...$this->commits, ...$page->items];
            $this->nextCursor = $page->nextCursor;
        }
    }

    /**
     * The nav bar's compose button: back to the top of the log, into the composer.
     */
    public function compose(): void
    {
        $this->composing = true;
        $this->focusComposer = true;
        $this->listKey++;
    }

    /**
     * A touch on the composer's field puts you in it.
     */
    public function composerFocused(): void
    {
        $this->composing = true;
        $this->focusComposer = false;
    }

    public function updatedBody(): void
    {
        $this->focusComposer = false;
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
        $this->focusComposer = false;
        $this->composerKey++;
        $this->commits = [$commit, ...$this->commits];
        $this->madeHere[] = $commit->id;
        $this->loaded = true;
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
        $this->focusComposer = false;
        $this->composerKey++;
    }

    /**
     * The log as it was kept last time, if there is one. Coming back to it, it's
     * laid over what's showing like any fresh page, so a change made elsewhere
     * shows at once.
     */
    private function showKept(): void
    {
        $json = $this->cached('log/'.$this->log);

        if ($json !== null) {
            $this->takeFresh($this->log, $json);
        }
    }

    /**
     * Lays a first page of the log over what's showing. Rows already there change
     * in place; newer ones wait for the pill, unless the log had nothing to show
     * or you pulled to refresh.
     *
     * @param  array<string, mixed>  $json
     */
    private function takeFresh(string $log, array $json, bool $showNewer = false): void
    {
        // An answer for the other log, which you've left.
        if ($log !== $this->log) {
            return;
        }

        $this->layFreshCommits($json, showNewer: $showNewer || ! $this->loaded);
        $this->loaded = true;
        $this->rememberLastVisit();
    }

    /**
     * The newest commit showing is the newest you've seen, as the web keeps it on
     * every load and after the pill. Commits waiting in the pill aren't seen yet.
     */
    private function rememberLastVisit(): void
    {
        if (($viewer = $this->me()) !== null) {
            app(LastVisit::class)->remember($viewer->id, $this->log, $this->commits);
        }
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
            'newCommitsLabel' => $this->newCommitsLabel(),
            'rows' => Timeline::feed(
                $this->commits,
                firstLane: Lane::Rail,
                firstSeenId: LastVisit::firstSeenId($this->commits, $this->lastVisitAt, $viewer?->id),
            ),
        ]);
    }
}
