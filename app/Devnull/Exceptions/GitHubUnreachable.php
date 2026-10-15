<?php

namespace App\Devnull\Exceptions;

/**
 * GitHub didn't answer, or answered with a server error. Worth trying again.
 */
final class GitHubUnreachable extends GitHubException
{
    public static function at(string $origin): self
    {
        return new self("fatal: unable to access '{$origin}': could not connect to the server");
    }

    public static function failing(string $origin, int $status): self
    {
        return new self("fatal: unable to access '{$origin}': the server answered HTTP {$status}");
    }
}
