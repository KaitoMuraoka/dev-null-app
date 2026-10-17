<?php

namespace App\NativeComponents\Concerns;

use App\Devnull\Client;
use App\Devnull\Data\Commit;
use App\Devnull\ReportReason;
use Closure;

/**
 * The sheet under a commit (see partials/commit-sheet). Report commit, in the
 * More menu, opens it as the web's Report dialog. As on the web, only someone
 * else's published commit can be reported, so only their menus have it.
 *
 * Quote opens the same sheet as a composer, over the commit being quoted, as
 * the web's Quote dialog. The Log opens it with nothing to quote, for a new
 * commit of your own, so you can write one without leaving where you are in
 * it. Once devnull has the commit the sheet goes, and a screen that lists
 * your new commits puts it in place (see showCommitted()).
 *
 * The sheet keeps its commit while it slides away, so it doesn't empty on the
 * way down, and forgets it and any draft once iOS says it's gone, however it
 * was closed.
 */
trait OpensCommitSheet
{
    /**
     * Whether the sheet is up, or on its way up.
     */
    public bool $sheetOpen = false;

    /**
     * The commit the sheet is for, as it was when it opened, until the sheet has gone.
     */
    public ?Commit $sheetCommit = null;

    /**
     * What the sheet shows: 'report' (the form), 'reported' (sent), 'quote', or 'compose' (a new commit, with no
     * commit of its own).
     */
    public string $sheetView = 'report';

    /**
     * The reason picked on the report form, or '' until one is.
     */
    public string $reportReason = '';

    public string $reportDetails = '';

    /**
     * The commit you're writing in the sheet, quoting its commit or not.
     */
    public string $sheetBody = '';

    /**
     * Why devnull didn't take the report or the commit, shown in the sheet.
     */
    public ?string $sheetError = null;

    /**
     * Report commit, in the More menu of someone else's published commit.
     */
    public function reportCommit(string $id): void
    {
        $commit = $this->shownCommit($id);

        if ($commit === null || ! $this->canReport($commit)) {
            return;
        }

        $this->forgetCommitSheet();
        $this->sheetCommit = $commit;
        $this->sheetOpen = true;
    }

    /**
     * Sends the report. The sheet says it was received, or keeps the form with why it wasn't.
     */
    public function sendReport(): void
    {
        $commit = $this->sheetCommit;
        $reason = ReportReason::tryFrom($this->reportReason);

        if ($commit === null || $this->sheetView !== 'report' || $reason === null) {
            return;
        }

        $details = trim($this->reportDetails);

        $sent = $this->attemptInSheet(function (Client $devnull) use ($commit, $reason, $details): bool {
            $devnull->reportCommit($commit->id, $reason, $details);

            return true;
        });

        if ($sent === true) {
            $this->sheetView = 'reported';
        }
    }

    /**
     * Quote, under someone else's published commit or one of your own, as on the web.
     */
    public function quoteCommit(string $id): void
    {
        $commit = $this->shownCommit($id);

        if ($commit === null || ! $this->canQuote($commit)) {
            return;
        }

        $this->forgetCommitSheet();
        $this->sheetCommit = $commit;
        $this->sheetView = 'quote';
        $this->sheetOpen = true;
    }

    /**
     * A new commit in the sheet, with nothing quoted.
     */
    public function composeInSheet(): void
    {
        $this->forgetCommitSheet();
        $this->sheetView = 'compose';
        $this->sheetOpen = true;
    }

    /**
     * Commits what's written in the sheet, quoting its commit if it's a quote. The sheet goes once devnull has it,
     * or keeps the draft with why it didn't take it.
     */
    public function commitFromSheet(): void
    {
        $quoted = $this->sheetView === 'quote' ? $this->sheetCommit : null;
        $body = trim($this->sheetBody);

        if (($quoted === null && $this->sheetView !== 'compose') || mb_strlen($body) < Commit::MinLength || mb_strlen($body) > Commit::MaxLength) {
            return;
        }

        $commit = $this->attemptInSheet(fn (Client $devnull): Commit => $devnull->createCommit($body, quotedCommitId: $quoted?->id));

        if ($commit === null) {
            return;
        }

        $this->sheetOpen = false;
        $this->showCommitted($commit);
    }

    /**
     * Done: the sheet goes.
     */
    public function hideCommitSheet(): void
    {
        $this->sheetOpen = false;
    }

    /**
     * iOS says the sheet has gone, swiped away or closed from here. What was drafted in it goes with it.
     */
    public function commitSheetClosed(): void
    {
        $this->forgetCommitSheet();
    }

    /**
     * A commit you just made, for a screen to put where it lists your new ones. Most don't.
     */
    protected function showCommitted(Commit $commit): void
    {
        //
    }

    private function canQuote(Commit $commit): bool
    {
        $viewer = $this->me();

        return $viewer !== null && ! $commit->isDeleted() && ($commit->isApproved() || $commit->author->id === $viewer->id);
    }

    private function canReport(Commit $commit): bool
    {
        $viewer = $this->me();

        return $viewer !== null && ! $commit->isDeleted() && $commit->isApproved() && $commit->author->id !== $viewer->id;
    }

    private function forgetCommitSheet(): void
    {
        $this->sheetOpen = false;
        $this->sheetCommit = null;
        $this->sheetView = 'report';
        $this->reportReason = '';
        $this->reportDetails = '';
        $this->sheetBody = '';
        $this->sheetError = null;
    }

    /**
     * Like attempt(), but a failure shows in the sheet rather than where the screen prints its errors.
     *
     * @template TResult
     *
     * @param  Closure(Client): TResult  $call
     * @return TResult|null
     */
    private function attemptInSheet(Closure $call): mixed
    {
        $screenError = $this->error;
        $this->sheetError = null;

        $result = $this->attempt($call);

        if ($result === null) {
            $this->sheetError = $this->error;
            $this->error = $screenError;
        }

        return $result;
    }
}
