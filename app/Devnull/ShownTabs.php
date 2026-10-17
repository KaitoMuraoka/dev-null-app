<?php

namespace App\Devnull;

use Illuminate\Support\Facades\Cache;

/**
 * The tab you last had open on each screen with tabs along its top, per user.
 *
 * Switching tabs at the bottom of the app mounts a screen afresh, so the Log,
 * Notifications and your own profile keep their tab here, and open on it again.
 */
final class ShownTabs
{
    /**
     * The tab last shown on the screen, or $default when it was none of $tabs.
     *
     * @param  list<string>  $tabs
     */
    public function get(?string $userId, string $screen, array $tabs, string $default): string
    {
        $tab = $userId === null ? null : Cache::get($this->key($userId, $screen));

        return is_string($tab) && in_array($tab, $tabs, true) ? $tab : $default;
    }

    public function remember(?string $userId, string $screen, string $tab): void
    {
        if ($userId !== null) {
            Cache::forever($this->key($userId, $screen), $tab);
        }
    }

    private function key(string $userId, string $screen): string
    {
        return "devnull.tab.{$userId}.{$screen}";
    }
}
