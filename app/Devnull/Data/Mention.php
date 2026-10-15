<?php

namespace App\Devnull\Data;

/**
 * Where the body mentions someone. The offset and length count Unicode code
 * points into the whole body and include the "@".
 */
final readonly class Mention
{
    public function __construct(public string $username, public int $offset, public int $length) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): ?self
    {
        $username = $data['username'] ?? null;
        $offset = $data['offset'] ?? null;
        $length = $data['length'] ?? null;

        if (! is_string($username) || $username === '' || ! is_int($offset) || ! is_int($length) || $offset < 0 || $length < 2) {
            return null;
        }

        return new self($username, $offset, $length);
    }
}
