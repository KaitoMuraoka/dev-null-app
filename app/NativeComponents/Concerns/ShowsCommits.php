<?php

namespace App\NativeComponents\Concerns;

use App\Devnull\Client;
use App\Devnull\Data\Commit;
use Closure;
use Native\Mobile\Facades\Browser;
use Native\Mobile\Facades\Share;

/**
 * The actions under a commit: Reply, the star, copy link and the bookmark,
 * plus opening its link preview or images in the in-app browser. Taps name
 * the commit rather than a URL, so a URL is never rebuilt from an attribute.
 */
trait ShowsCommits
{
    /**
     * Every commit the screen is showing.
     *
     * @return iterable<Commit>
     */
    abstract protected function shownCommits(): iterable;

    /**
     * Runs $update over every commit the screen is showing, keeping what it returns.
     *
     * @param  Closure(Commit): Commit  $update
     */
    abstract protected function updateShownCommits(Closure $update): void;

    /**
     * @param  array<string, mixed>  $data
     */
    abstract protected function openInTab(string $path, array $data = []): void;

    public function openCommit(string $id): void
    {
        $this->openInTab('/commits/'.$id);
    }

    public function openProfile(string $username): void
    {
        $this->openInTab('/users/'.$username);
    }

    /**
     * Opens the commit with its reply composer focused.
     */
    public function replyTo(string $id): void
    {
        $this->openInTab('/commits/'.$id, ['reply' => true]);
    }

    /**
     * Stars or unstars straight away, then takes the count devnull sends back,
     * or puts the star back if it says no. Like the web, only an approved commit
     * or your own can be starred.
     */
    public function toggleStar(string $id): void
    {
        $commit = $this->shownCommit($id);

        if ($commit === null || ! $this->canReactTo($commit)) {
            return;
        }

        $this->react($commit, $commit->withStar(! $commit->starred), fn ($devnull) => $commit->starred
            ? $devnull->unstar($id)
            : $devnull->star($id));
    }

    /**
     * Saves or unsaves the commit privately, the same way.
     */
    public function toggleBookmark(string $id): void
    {
        $commit = $this->shownCommit($id);

        if ($commit === null || $commit->isSaved === null || ! $this->canReactTo($commit)) {
            return;
        }

        $this->react($commit, $commit->withSaved(! $commit->isSaved), fn ($devnull) => $commit->isSaved
            ? $devnull->unbookmark($id)
            : $devnull->bookmark($id));
    }

    public function openLinkPreview(string $id): void
    {
        $this->openInBrowser($this->shownCommit($id)?->linkPreview?->url);
    }

    public function openImage(string $id, int $index = 0): void
    {
        $this->openInBrowser(($this->shownCommit($id)?->images[$index] ?? null)?->url);
    }

    /**
     * The web's copy-link button. The share sheet has Copy, and AirDrop and Messages besides.
     */
    public function shareCommit(string $id): void
    {
        $commit = $this->shownCommit($id);

        if ($commit === null || $commit->isDeleted()) {
            return;
        }

        Share::url('', '', $commit->htmlUrl ?? rtrim((string) config('devnull.url'), '/')."/@{$commit->author->username}/commits/{$commit->id}");
    }

    private function canReactTo(Commit $commit): bool
    {
        return ! $commit->isDeleted() && ($commit->isApproved() || $commit->author->id === $this->me()?->id);
    }

    /**
     * @param  Closure(Client): Commit  $call
     */
    private function react(Commit $before, Commit $optimistic, Closure $call): void
    {
        $this->replaceShownCommit($optimistic);

        $fresh = $this->attempt($call);

        $this->replaceShownCommit($fresh instanceof Commit ? $before->withReactionsFrom($fresh) : $before);
    }

    private function replaceShownCommit(Commit $replacement): void
    {
        $this->updateShownCommits(fn (Commit $commit): Commit => $commit->id === $replacement->id ? $replacement : $commit);
    }

    private function shownCommit(string $id): ?Commit
    {
        foreach ($this->shownCommits() as $commit) {
            if ($commit->id === $id) {
                return $commit;
            }
        }

        return null;
    }

    private function openInBrowser(?string $url): void
    {
        if ($url !== null && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            Browser::inApp($url);
        }
    }
}
