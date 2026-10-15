<?php

namespace App\Devnull\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * GitHub said no, or said something this app doesn't understand.
 */
class GitHubException extends RuntimeException
{
    /**
     * An OAuth error from github.com, with GitHub's own description when it sent one.
     */
    public static function refused(Response $response): self
    {
        $error = $response->json('error_description') ?? $response->json('error');

        return new self(is_string($error) && $error !== ''
            ? "fatal: github.com refused: {$error}"
            : "fatal: github.com refused (HTTP {$response->status()})");
    }

    /**
     * A REST API error from api.github.com, with the first validation message when there is one.
     */
    public static function fromApi(Response $response, string $doing): self
    {
        $message = $response->json('message');
        $detail = $response->json('errors.0.message');

        $reason = collect([$message, $detail])
            ->filter(fn (mixed $part): bool => is_string($part) && $part !== '')
            ->implode(': ');

        return new self("fatal: {$doing}".($reason === '' ? '' : ": {$reason}")." (HTTP {$response->status()})");
    }
}
