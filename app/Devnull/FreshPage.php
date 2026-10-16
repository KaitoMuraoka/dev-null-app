<?php

namespace App\Devnull;

use App\Devnull\Data\Page;

/**
 * A fresh first page laid over a list a screen is already showing, newest first.
 *
 * Rows already shown take their fresh copies in place, so counts, stars and
 * edits change quietly, and rows devnull no longer sends go. The exception is
 * a commit you just made on this screen, which an answer sent before you made
 * it can't know about yet.
 *
 * What's newer than anything shown waits in $newer, for the screen to put on
 * top when you ask (the new commits pill), so nothing moves under your finger.
 * Pages loaded further down stay as they are.
 *
 * When the fresh page has nothing in common with what's shown, so many came
 * in that there may be a gap, showing them replaces the list ($replacesAll).
 *
 * @template TItem of object
 */
final readonly class FreshPage
{
    /**
     * @param  list<TItem>  $items  the list to show now
     * @param  list<TItem>  $newer  what's newer than anything shown
     */
    public function __construct(
        public array $items,
        public ?string $nextCursor,
        public array $newer = [],
        public bool $replacesAll = false,
        public ?string $freshCursor = null,
    ) {}

    /**
     * @template T of object
     *
     * @param  list<T>  $shown
     * @param  Page<T>  $fresh
     * @param  list<string>  $madeHere  ids of commits you made on this screen
     * @return self<T>
     */
    public static function over(array $shown, ?string $shownCursor, Page $fresh, array $madeHere = []): self
    {
        if ($shown === []) {
            return new self($fresh->items, $fresh->nextCursor);
        }

        $shownAt = array_flip(self::ids($shown));
        $firstKnown = null;

        foreach ($fresh->items as $index => $item) {
            if (isset($shownAt[$item->id])) {
                $firstKnown = $index;

                break;
            }
        }

        if ($firstKnown === null) {
            return new self($shown, $shownCursor, $fresh->items, replacesAll: true, freshCursor: $fresh->nextCursor);
        }

        $newer = array_slice($fresh->items, 0, $firstKnown);
        $window = array_slice($fresh->items, $firstKnown);
        $windowIds = array_flip(self::ids($window));

        $last = max(array_map(fn (object $item): int => $shownAt[$item->id] ?? -1, $window));
        $madeHere = array_flip($madeHere);
        $before = array_filter(array_slice($shown, 0, $shownAt[$window[0]->id]), fn (object $item): bool => isset($madeHere[$item->id]));
        $after = array_slice($shown, $last + 1);
        $keep = fn (object $item): bool => ! isset($windowIds[$item->id]);

        return new self(
            [...array_filter($before, $keep), ...$window, ...array_filter($after, $keep)],
            $last === count($shown) - 1 ? $fresh->nextCursor : $shownCursor,
            $newer,
            freshCursor: $fresh->nextCursor,
        );
    }

    /**
     * Whether there's anything to put on top.
     */
    public function hasNewer(): bool
    {
        return $this->newer !== [];
    }

    /**
     * The list with what's newer on top, as the pill shows it.
     *
     * @return self<TItem>
     */
    public function withNewer(): self
    {
        if ($this->replacesAll) {
            return new self($this->newer, $this->freshCursor);
        }

        $newerIds = array_flip(self::ids($this->newer));

        return new self(
            [...$this->newer, ...array_values(array_filter($this->items, fn (object $item): bool => ! isset($newerIds[$item->id])))],
            $this->nextCursor,
        );
    }

    /**
     * @param  list<object>  $items
     * @return list<string>
     */
    private static function ids(array $items): array
    {
        return array_map(fn (object $item): string => (string) $item->id, $items);
    }
}
