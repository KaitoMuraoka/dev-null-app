<?php

namespace App\Devnull;

use App\Devnull\Data\Commit;
use App\Devnull\Data\TimelineRow;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Lays commits out on the graph rail the way the web does since it put author
 * avatars on the rail.
 *
 * A run of commits by one author shows their avatar once, on the first commit,
 * and a lane dot on the rest. A day marker or the last-visit marker between two
 * commits starts a new run.
 */
final class Timeline
{
    /**
     * A feed, like the web's CommitFeed: a day marker wherever the day changes and
     * the last-visit marker above the first commit you had already seen.
     *
     * @param  list<Commit>  $commits
     * @return list<TimelineRow>
     */
    public static function feed(array $commits, int|string|null $firstLane = null, ?string $firstSeenId = null, ?CarbonInterface $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $rows = [];
        $previous = null;

        foreach ($commits as $commit) {
            $previousLane = $previous?->lane() ?? $firstLane;
            $isFirstSeen = $firstSeenId !== null && $commit->id === $firstSeenId;
            $startsDay = self::startsDay($previous, $commit, $now);

            if ($isFirstSeen) {
                $rows[] = TimelineRow::lastVisit($previousLane);
            }

            if ($startsDay && $commit->createdAt !== null) {
                $rows[] = TimelineRow::day(Display::dayLabel($commit->createdAt, $now), $previousLane);
            }

            $rows[] = TimelineRow::commit($commit, $previousLane, ! $startsDay && ! $isFirstSeen && self::continuesRun($previous, $commit));
            $previous = $commit;
        }

        return $rows;
    }

    /**
     * A commit page's ancestors and the commit itself, on one rail with no day
     * markers, so someone replying to themselves shows their avatar once.
     *
     * @param  list<Commit>  $commits
     * @return list<TimelineRow>
     */
    public static function thread(array $commits): array
    {
        $rows = [];
        $previous = null;

        foreach ($commits as $commit) {
            $rows[] = TimelineRow::commit($commit, $previous?->lane(), self::continuesRun($previous, $commit));
            $previous = $commit;
        }

        return $rows;
    }

    /**
     * Whether a commit carries on the run of the one above it: the same author, and
     * neither of them deleted.
     */
    public static function continuesRun(?Commit $previous, Commit $commit): bool
    {
        return $previous !== null
            && ! $previous->isDeleted()
            && ! $commit->isDeleted()
            && $previous->user->id === $commit->user->id;
    }

    private static function startsDay(?Commit $previous, Commit $commit, CarbonInterface $now): bool
    {
        if ($previous === null) {
            return true;
        }

        if ($previous->createdAt === null || $commit->createdAt === null) {
            return false;
        }

        return Display::dayKey($previous->createdAt, $now) !== Display::dayKey($commit->createdAt, $now);
    }
}
