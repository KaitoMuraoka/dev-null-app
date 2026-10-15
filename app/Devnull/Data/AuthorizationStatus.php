<?php

namespace App\Devnull\Data;

/**
 * Where a device flow stands, named after GitHub's own error codes.
 */
enum AuthorizationStatus: string
{
    case Granted = 'granted';
    case Pending = 'authorization_pending';
    case SlowDown = 'slow_down';
    case Expired = 'expired_token';
    case Denied = 'access_denied';

    /**
     * GitHub's docs name the expiry error both `expired_token` and `token_expired`.
     */
    public static function fromError(string $error): ?self
    {
        return $error === 'token_expired' ? self::Expired : self::tryFrom($error);
    }
}
