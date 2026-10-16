<?php

namespace App\Devnull;

/**
 * The sign-in under way, held in memory only.
 *
 * It keeps the attempt the browser is showing, and the browser's answer once
 * devnull://auth/callback brings it back, until onboarding takes both. A new
 * attempt replaces the old one, and an answer is taken once, so each attempt
 * is good for one answer whatever it says. Nothing here is ever written down.
 */
final class SignIn
{
    private ?SignInAttempt $attempt = null;

    /**
     * @var array<string, string>|null
     */
    private ?array $callback = null;

    public function begin(): SignInAttempt
    {
        $this->callback = null;

        return $this->attempt = SignInAttempt::start();
    }

    /**
     * @param  array<string, string>  $query
     */
    public function receive(array $query): void
    {
        $this->callback = $query;
    }

    public function hasCallback(): bool
    {
        return $this->callback !== null;
    }

    /**
     * The browser's answer and the attempt it should answer, forgetting both.
     *
     * @return array{0: array<string, string>, 1: SignInAttempt|null}|null
     */
    public function take(): ?array
    {
        if ($this->callback === null) {
            return null;
        }

        $taken = [$this->callback, $this->attempt];
        $this->callback = null;
        $this->attempt = null;

        return $taken;
    }
}
