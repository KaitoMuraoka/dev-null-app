<?php

namespace App\NativeComponents\Concerns;

use App\Devnull\Data\Commit;
use Native\Mobile\Facades\Browser;
use Native\Mobile\Facades\Share;

/**
 * Opens what a commit links to: its link preview, or one of its images, in
 * the in-app browser, and shares a link to the commit itself. Taps name the
 * commit rather than the URL, so the URL is never rebuilt from an attribute.
 */
trait ShowsCommits
{
    /**
     * Every commit the screen is showing.
     *
     * @return iterable<Commit>
     */
    abstract protected function shownCommits(): iterable;

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

        Share::url('', '', $commit->url ?? rtrim((string) config('devnull.url'), '/')."/@{$commit->user->username}/commits/{$commit->id}");
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
