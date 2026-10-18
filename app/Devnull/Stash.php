<?php

namespace App\Devnull;

use Illuminate\Support\Facades\Cache;

/**
 * What you wrote and didn't send, per user, as the web's useStash keeps it.
 *
 * Drafts go by the web's names: 'commit' for the Log's composer, which its
 * sheet shares, 'quote-{id}' for a quote of a commit and 'reply-{id}' for a
 * reply to one. A screen keeps a draft as its field changes, and forgets it
 * once it's sent or the field is emptied. Signing out forgets them all (see
 * Account::forget).
 */
final class Stash
{
    /**
     * How many drafts are kept per user. The one written longest ago goes first.
     */
    public const int MaxDrafts = 50;

    /**
     * The draft kept under $name, or '' when there's none.
     */
    public function get(?string $userId, string $name): string
    {
        return $this->drafts($userId)[$name] ?? '';
    }

    /**
     * Keeps $body under $name. An empty body forgets the draft.
     */
    public function put(?string $userId, string $name, string $body): void
    {
        if ($userId === null) {
            return;
        }

        $drafts = $this->drafts($userId);

        if ($body === '' && ! array_key_exists($name, $drafts)) {
            return;
        }

        unset($drafts[$name]);

        if ($body !== '') {
            $drafts = array_slice([...$drafts, $name => $body], -self::MaxDrafts, preserve_keys: true);
        }

        $drafts === []
            ? Cache::forget($this->key($userId))
            : Cache::forever($this->key($userId), $drafts);
    }

    /**
     * The names of every draft kept, for the Quote and Reply buttons that mark one.
     *
     * @return list<string>
     */
    public function names(?string $userId): array
    {
        return array_map(strval(...), array_keys($this->drafts($userId)));
    }

    public function forget(?string $userId): void
    {
        if ($userId !== null) {
            Cache::forget($this->key($userId));
        }
    }

    /**
     * @return array<string, string>
     */
    private function drafts(?string $userId): array
    {
        $drafts = $userId === null ? null : Cache::get($this->key($userId));

        return is_array($drafts) ? array_filter($drafts, fn (mixed $body): bool => is_string($body) && $body !== '') : [];
    }

    private function key(string $userId): string
    {
        return "devnull.stash.{$userId}";
    }
}
