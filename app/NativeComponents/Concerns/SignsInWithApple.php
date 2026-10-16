<?php

namespace App\NativeComponents\Concerns;

use App\Devnull\AppleSignIn;
use Codingwithrk\NativephpSocialAuth\Events\AppleSignInCompleted;
use Codingwithrk\NativephpSocialAuth\Events\SignInFailed;
use Native\Mobile\Attributes\On;
use SensitiveParameter;

/**
 * Opens Apple's sheet from a screen and hands the screen Apple's answer.
 *
 * The answer arrives as a native event and is only used when it echoes the
 * nonce of the attempt this phone started. The identity token goes straight
 * to the screen, which sends it to devnull: it's never kept. Closing Apple's
 * sheet does nothing at all.
 */
trait SignsInWithApple
{
    /**
     * Uses the identity token Apple signed, with the raw nonce it was asked for.
     */
    abstract protected function useAppleToken(string $identityToken, string $nonce): void;

    abstract protected function appleFailed(string $message): void;

    protected function startAppleSignIn(): void
    {
        if (! app(AppleSignIn::class)->start()) {
            $this->appleFailed(AppleSignIn::CouldNotOpen);
        }
    }

    #[On(AppleSignInCompleted::class)]
    public function appleSignedIn(#[SensitiveParameter] string $identityToken = '', #[SensitiveParameter] string $nonce = ''): void
    {
        $nonce = app(AppleSignIn::class)->take($nonce);

        if ($nonce === null) {
            $this->appleFailed(AppleSignIn::NotStartedHere);

            return;
        }

        if ($identityToken === '') {
            $this->appleFailed(AppleSignIn::NoToken);

            return;
        }

        $this->useAppleToken($identityToken, $nonce);
    }

    #[On(SignInFailed::class)]
    public function appleSignInFailed(string $provider = '', string $error = '', string $errorCode = ''): void
    {
        if ($provider !== 'apple') {
            return;
        }

        app(AppleSignIn::class)->forget();

        if ($errorCode === AppleSignIn::Cancelled) {
            return;
        }

        $this->appleFailed(rtrim('fatal: Sign in with Apple failed. '.$error));
    }
}
