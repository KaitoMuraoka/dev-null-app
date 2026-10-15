<?php

namespace App\Devnull\Data;

/**
 * One cell of the activity strip: its commits, and how strongly to tint it.
 */
final readonly class ActivityCell
{
    public function __construct(public int $count, public int $level, public ?string $label = null) {}

    /**
     * How much of the owner's lane shows through, as the web mixes it into the surface.
     */
    public function opacity(): float
    {
        return [0 => 0.0, 1 => 0.32, 2 => 0.56, 3 => 0.8][$this->level] ?? 1.0;
    }
}
