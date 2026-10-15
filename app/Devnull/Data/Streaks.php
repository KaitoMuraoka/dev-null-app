<?php

namespace App\Devnull\Data;

/**
 * A profile's commit streaks in days: the longest ever, and the one still going.
 */
final readonly class Streaks
{
    public function __construct(public int $longest = 0, public int $current = 0) {}

    public static function fromArray(mixed $data): self
    {
        if (! is_array($data)) {
            return new self;
        }

        return new self(
            longest: is_int($data['longest'] ?? null) ? max(0, $data['longest']) : 0,
            current: is_int($data['current'] ?? null) ? max(0, $data['current']) : 0,
        );
    }
}
