<?php

namespace App\NativeComponents\Concerns;

use App\Devnull\Data\Commit;
use App\Devnull\Data\Page;
use App\Devnull\FreshPage;

/**
 * A list of commits that takes fresh first pages without moving under your
 * finger: rows already showing change in place, and newer commits wait in the
 * new commits pill until you tap it (see FreshPage). They're already fetched
 * by then, so the tap only puts them on top and goes back up the list.
 *
 * EDGE tells PHP nothing about where a list is scrolled, so the screen can't
 * know you're at the top unless you pulled to refresh there. That's when new
 * commits go straight in, and when the list had nothing to show.
 */
trait HoldsNewCommits
{
    /** @var list<Commit> */
    public array $commits = [];

    public ?string $nextCursor = null;

    /**
     * Commits newer than anything showing, fetched and waiting for the pill.
     *
     * @var list<Commit>
     */
    public array $newCommits = [];

    /**
     * The new commits have nothing in common with the list, so showing them replaces it, from this cursor on.
     */
    public bool $newCommitsReplaceList = false;

    public ?string $newCommitsCursor = null;

    /**
     * Mounts a fresh list when it changes, which puts it back at the top.
     */
    public int $listKey = 0;

    /**
     * Commits you made on this screen. They stay on top when an answer that left before you made them lands.
     *
     * @var list<string>
     */
    public array $madeHere = [];

    /**
     * The pill: puts the new commits on top and goes back to the top.
     */
    public function showNewCommits(): void
    {
        if ($this->newCommits === []) {
            return;
        }

        $this->putNewCommitsOnTop();
        $this->listKey++;
    }

    /**
     * How the pill reads, as on the web: "3 new commits". A "+" says more came in than one page holds.
     */
    public function newCommitsLabel(): string
    {
        $count = count($this->newCommits);

        return $count.($this->newCommitsReplaceList && $this->newCommitsCursor !== null ? '+' : '').' '.($count === 1 ? 'new commit' : 'new commits');
    }

    /**
     * Lays a fresh first page over the list. Newer commits wait for the pill
     * unless $showNewer says you asked for them.
     *
     * @param  array<string, mixed>  $json
     */
    protected function layFreshCommits(array $json, bool $showNewer = false): void
    {
        $fresh = FreshPage::over($this->commits, $this->nextCursor, Page::fromArray($json, Commit::fromArray(...)), $this->madeHere);

        $this->commits = $fresh->items;
        $this->nextCursor = $fresh->nextCursor;
        $this->newCommits = $fresh->newer;
        $this->newCommitsReplaceList = $fresh->replacesAll;
        $this->newCommitsCursor = $fresh->freshCursor;

        if ($showNewer) {
            $this->putNewCommitsOnTop();
        }
    }

    protected function putNewCommitsOnTop(): void
    {
        if ($this->newCommits === []) {
            return;
        }

        $shown = (new FreshPage($this->commits, $this->nextCursor, $this->newCommits, $this->newCommitsReplaceList, $this->newCommitsCursor))->withNewer();

        if ($this->newCommitsReplaceList) {
            $this->threads = [];
        }

        $this->commits = $shown->items;
        $this->nextCursor = $shown->nextCursor;
        $this->forgetNewCommits();
    }

    protected function forgetNewCommits(): void
    {
        $this->newCommits = [];
        $this->newCommitsReplaceList = false;
        $this->newCommitsCursor = null;
    }
}
