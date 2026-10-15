<?php

namespace App\Devnull\Data;

/**
 * One row on the graph rail: a commit, a day marker or the last-visit marker.
 *
 * $previousLane is the lane of the segment running into the row from above,
 * or null when nothing runs into it.
 */
final readonly class TimelineRow
{
    private function __construct(
        public string $kind,
        public int|string|null $previousLane,
        public ?Commit $commit = null,
        public bool $continuesRun = false,
        public string $label = '',
    ) {}

    /**
     * A commit, drawn with its author's avatar on the rail, or with a lane dot when
     * it carries on a run by the same author.
     */
    public static function commit(Commit $commit, int|string|null $previousLane, bool $continuesRun = false): self
    {
        return new self('commit', $previousLane, $commit, $continuesRun);
    }

    public static function day(string $label, int|string|null $previousLane): self
    {
        return new self('day', $previousLane, label: $label);
    }

    public static function lastVisit(int|string|null $previousLane): self
    {
        return new self('last-visit', $previousLane);
    }

    public function isCommit(): bool
    {
        return $this->kind === 'commit';
    }

    public function isDayMarker(): bool
    {
        return $this->kind === 'day';
    }

    public function isLastVisitMarker(): bool
    {
        return $this->kind === 'last-visit';
    }
}
