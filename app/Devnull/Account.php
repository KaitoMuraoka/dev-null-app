<?php

namespace App\Devnull;

use App\Devnull\Data\User;
use Illuminate\Support\Facades\Cache;

/**
 * The devnull user this device has been verified as.
 *
 * Nothing here is secret: the key that proves it lives in the Keychain.
 */
final class Account
{
    private const string CacheKey = 'devnull.account';

    private const string UnreadKey = 'devnull.unread-notifications';

    public function user(): ?User
    {
        $attributes = Cache::get(self::CacheKey);

        return is_array($attributes) ? User::fromArray($attributes) : null;
    }

    public function remember(User $user): void
    {
        Cache::forever(self::CacheKey, $user->toArray());
    }

    /**
     * How many notifications were unread when devnull last said, for the tab badge.
     */
    public function unreadNotifications(): int
    {
        $count = Cache::get(self::UnreadKey);

        return is_int($count) ? $count : 0;
    }

    public function rememberUnreadNotifications(int $count): void
    {
        Cache::forever(self::UnreadKey, max(0, $count));
    }

    /**
     * Signs this phone out: who it was, the badge, and every answer kept for a quick start.
     */
    public function forget(): void
    {
        app(ResponseCache::class)->flush();

        Cache::forget(self::CacheKey);
        Cache::forget(self::UnreadKey);
    }
}
