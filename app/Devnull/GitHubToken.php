<?php

namespace App\Devnull;

use SensitiveParameter;
use SensitiveParameterValue;

/**
 * The GitHub token from the device flow, held in memory only for as long as
 * devnull needs it to approve this phone.
 *
 * devnull looks for the key on GitHub before it looks at the token, and
 * GitHub can take a few seconds to list a new key, so a key sync may have to
 * be asked again a little later. The token waits here, not on the screen, and
 * is dropped the moment devnull answers for good. It's wrapped so it never
 * shows up in a dump or a stack trace, and can't be serialized to disk.
 */
final class GitHubToken
{
    private ?SensitiveParameterValue $token = null;

    public function hold(#[SensitiveParameter] string $token): void
    {
        $this->token = new SensitiveParameterValue($token);
    }

    public function get(): ?string
    {
        $token = $this->token?->getValue();

        return is_string($token) && $token !== '' ? $token : null;
    }

    public function forget(): void
    {
        $this->token = null;
    }
}
