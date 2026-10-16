<?php

namespace App\NativeComponents\Concerns;

use App\Devnull\Data\Commit;
use App\Devnull\Data\Page;

/**
 * "› N replies" under a commit in a feed opens its replies in place, the way
 * the web's thread toggle does, and "Hide replies" closes them again. One
 * level deep: a reply's own replies open its page.
 *
 * A thread opened before opens at once from the cache, and refreshes behind.
 */
trait ExpandsThreads
{
    /**
     * The replies of each commit opened in place, by the commit's id.
     *
     * @var array<string, list<Commit>>
     */
    public array $threads = [];

    public function toggleReplies(string $id): void
    {
        if (array_key_exists($id, $this->threads)) {
            unset($this->threads[$id]);

            return;
        }

        $kept = $this->cached('commits/'.$id.'/replies');

        if ($kept === null) {
            $this->loadThread($id);

            return;
        }

        $this->takeThread($id, $kept);
        $this->refreshThread($id);
    }

    /**
     * Asks again for every open thread, so a reply made or deleted elsewhere shows up.
     */
    protected function refreshThreads(): void
    {
        foreach (array_keys($this->threads) as $id) {
            $this->refreshThread((string) $id);
        }
    }

    private function refreshThread(string $id): void
    {
        $this->refresh('commits/'.$id.'/replies', [], function (array $json) use ($id): void {
            // Closed while it was on its way.
            if (array_key_exists($id, $this->threads)) {
                $this->takeThread($id, $json);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function takeThread(string $id, array $json): void
    {
        $page = Page::fromArray($json, Commit::fromArray(...));

        // A thread whose last reply went closes, so its commit gets its hairline back.
        if ($page->items === []) {
            unset($this->threads[$id]);

            return;
        }

        $this->threads[$id] = $page->items;
    }

    /**
     * @return list<Commit>
     */
    protected function threadCommits(): array
    {
        return array_merge([], ...array_values($this->threads));
    }

    private function loadThread(string $id): void
    {
        $page = $this->attempt(fn ($devnull) => $devnull->replies($id));

        if ($page === null) {
            return;
        }

        if ($page->items === []) {
            unset($this->threads[$id]);

            return;
        }

        $this->threads[$id] = $page->items;
    }
}
