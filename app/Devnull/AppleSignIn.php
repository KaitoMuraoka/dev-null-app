<?php

namespace App\Devnull;

use Codingwithrk\NativephpSocialAuth\Facades\NativephpSocialAuth;
use Native\Mobile\Platform;
use SensitiveParameterValue;

/**
 * The Sign in with Apple attempt under way, held in memory only.
 *
 * Each attempt gets a fresh nonce. The plugin gives Apple its SHA-256 as hex,
 * so the identity token Apple signs carries that hash, and devnull checks it
 * against the raw nonce the app sends next to the token. The plugin echoes the
 * raw nonce back with Apple's answer, so an answer is only taken for the
 * attempt it belongs to, and only once. Nothing here is ever written down.
 */
final class AppleSignIn
{
    public const string NotStartedHere = "fatal: that Apple sign-in didn't start on this phone, so it was ignored.";

    public const string CouldNotOpen = 'fatal: could not open Sign in with Apple.';

    public const string NoToken = 'fatal: Apple sent no identity token.';

    /**
     * The plugin's code for someone closing Apple's sheet.
     */
    public const string Cancelled = 'USER_CANCELLED';

    private ?SensitiveParameterValue $nonce = null;

    /**
     * Apple has no sign-in on Android, so the app only offers it on iOS.
     */
    public static function isAvailable(): bool
    {
        return Platform::isIos();
    }

    /**
     * Opens Apple's sheet with a new nonce, replacing any attempt before it.
     * It asks for no name or email: devnull only needs to know it's the same
     * Apple ID, since the account is always the GitHub one.
     */
    public function start(): bool
    {
        $nonce = bin2hex(random_bytes(32));
        $this->nonce = new SensitiveParameterValue($nonce);

        if (NativephpSocialAuth::appleSignIn(scopes: [], nonce: $nonce)) {
            return true;
        }

        $this->forget();

        return false;
    }

    /**
     * The raw nonce, when Apple's answer echoes the attempt under way. Either
     * way that attempt is spent.
     */
    public function take(?string $echoedNonce): ?string
    {
        $nonce = $this->nonce?->getValue();
        $this->forget();

        if (! is_string($nonce) || ! is_string($echoedNonce) || ! hash_equals($nonce, $echoedNonce)) {
            return null;
        }

        return $nonce;
    }

    public function forget(): void
    {
        $this->nonce = null;
    }
}
