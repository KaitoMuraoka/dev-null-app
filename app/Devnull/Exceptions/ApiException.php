<?php

namespace App\Devnull\Exceptions;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * A devnull API error, carrying the server's message as is.
 */
class ApiException extends RuntimeException
{
    /**
     * @param  array<string, list<string>>  $errors
     * @param  int|null  $retryAfter  How many seconds the server asked to wait before trying again.
     */
    public function __construct(string $message, public readonly int $status = 0, public readonly array $errors = [], public readonly ?int $retryAfter = null)
    {
        parent::__construct($message, $status);
    }

    public static function fromResponse(Response $response): self
    {
        $message = $response->json('message');

        if (! is_string($message) || $message === '') {
            $message = "fatal: the remote end hung up unexpectedly (HTTP {$response->status()})";
        }

        $errors = $response->json('errors');
        $errors = is_array($errors) ? array_map(fn (mixed $messages): array => array_values(array_filter((array) $messages, 'is_string')), $errors) : [];

        return match ($response->status()) {
            401 => new Unauthenticated($message, 401),
            422 => new ApiException($message, 422, $errors),
            default => new ApiException($message, $response->status(), retryAfter: self::retryAfter($response)),
        };
    }

    /**
     * The Retry-After header, which may be a number of seconds or a date.
     */
    private static function retryAfter(Response $response): ?int
    {
        $header = trim($response->header('Retry-After'));

        if ($header === '') {
            return null;
        }

        if (ctype_digit($header)) {
            return (int) $header;
        }

        $at = strtotime($header);

        return $at === false ? null : max(0, $at - CarbonImmutable::now()->getTimestamp());
    }

    /**
     * The first validation message, falling back to the server's message.
     */
    public function firstError(): string
    {
        foreach ($this->errors as $messages) {
            if (isset($messages[0])) {
                return $messages[0];
            }
        }

        return $this->getMessage();
    }
}
