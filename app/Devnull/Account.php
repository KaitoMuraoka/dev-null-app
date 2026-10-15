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

    public function user(): ?User
    {
        $attributes = Cache::get(self::CacheKey);

        return is_array($attributes) ? User::fromArray($attributes) : null;
    }

    public function remember(User $user): void
    {
        Cache::forever(self::CacheKey, $user->toArray());
    }

    public function forget(): void
    {
        Cache::forget(self::CacheKey);
    }
}
