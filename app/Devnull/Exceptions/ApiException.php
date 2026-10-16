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
     * @param  string|null  $errorCode  The machine-readable code devnull sends next to the message, such as "device_not_approved".
     */
    public function __construct(string $message, public readonly int $status = 0, public readonly array $errors = [], public readonly ?int $retryAfter = null, public readonly ?string $errorCode = null)
    {
        parent::__construct($message, $status);
    }

    public static function fromResponse(Response $response): self
    {
        return self::fromParts($response->status(), $response->json(), $response->header('Retry-After'));
    }

    /**
     * The error devnull answered with, from its status, decoded body and Retry-After
     * header. A background refresh only has these, not the response itself.
     */
    public static function fromParts(int $status, mixed $json, string $retryAfter = ''): self
    {
        $json = is_array($json) ? $json : [];
        $message = $json['message'] ?? null;

        if (! is_string($message) || $message === '') {
            $message = "fatal: the remote end hung up unexpectedly (HTTP {$status})";
        }

        $errors = is_array($json['errors'] ?? null) ? $json['errors'] : [];
        $errors = array_map(fn (mixed $messages): array => array_values(array_filter((array) $messages, 'is_string')), $errors);
        $code = $json['code'] ?? null;
        $code = is_string($code) && $code !== '' ? $code : null;

        return match ($status) {
            401 => new Unauthenticated($message, 401, errorCode: $code),
            422 => new ApiException($message, 422, $errors, errorCode: $code),
            default => new ApiException($message, $status, retryAfter: self::retryAfter($retryAfter), errorCode: $code),
        };
    }

    /**
     * What a request that never reached devnull says.
     */
    public static function couldNotConnect(string $baseUrl): self
    {
        return new self("fatal: unable to access '{$baseUrl}': could not connect to the server");
    }

    /**
     * The Retry-After header, which may be a number of seconds or a date.
     */
    private static function retryAfter(string $header): ?int
    {
        $header = trim($header);

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
