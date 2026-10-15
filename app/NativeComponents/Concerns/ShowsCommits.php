<?php

namespace App\NativeComponents\Concerns;

use App\Devnull\Data\Commit;
use Native\Mobile\Facades\Browser;

/**
 * Opens what a commit links to: its link preview, or one of its images, in
 * the in-app browser. Taps name the commit rather than the URL, so the URL
 * is never rebuilt from an attribute.
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
