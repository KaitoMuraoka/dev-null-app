<?php

namespace App\NativeComponents\Concerns;

use App\Devnull\Data\Commit;

/**
 * "› N replies" under a commit in a feed opens its replies in place, the way
 * the web's thread toggle does, and "Hide replies" closes them again. One
 * level deep: a reply's own replies open its page.
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

        $this->loadThread($id);
    }

    /**
     * Fetches every open thread again, so a reply made or deleted elsewhere shows up.
     */
    protected function reloadThreads(): void
    {
        foreach (array_keys($this->threads) as $id) {
            $this->loadThread((string) $id);
        }
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

        if ($page !== null) {
            $this->threads[$id] = $page->items;
        }
    }
}
