<?php

namespace App\Devnull\Data;

/**
 * A run of a paragraph: plain prose, a mention, or inline code.
 */
final readonly class BodyRun
{
    public function __construct(public string $text, public ?string $username = null, public bool $isCode = false) {}

    public function isMention(): bool
    {
        return $this->username !== null;
    }
}
