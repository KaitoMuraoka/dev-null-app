<?php

namespace App\Devnull;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * How the machine prints things: relative times and short hashes, as the web does.
 */
final class Display
{
    public static function relativeTime(?CarbonInterface $date, ?CarbonInterface $now = null): string
    {
        if ($date === null) {
            return '';
        }

        $now ??= CarbonImmutable::now();
        $seconds = (int) round($now->getTimestamp() - $date->getTimestamp());

        return match (true) {
            $seconds < 60 => 'now',
            $seconds < 3600 => intdiv($seconds, 60).'m',
            $seconds < 86400 => intdiv($seconds, 3600).'h',
            $seconds < 604800 => intdiv($seconds, 86400).'d',
            $date->year === $now->year => $date->format('M j'),
            default => $date->format('M j, Y'),
        };
    }

    /**
     * The label of a day marker in a feed, worded like the web: "Today", "Yesterday",
     * then "Tue, Oct 13", with the year once it isn't this year. Days are counted in
     * the app's time zone, since PHP can't see the device's.
     */
    public static function dayLabel(CarbonInterface $date, ?CarbonInterface $now = null): string
    {
        $now = CarbonImmutable::instance($now ?? CarbonImmutable::now());
        $day = CarbonImmutable::instance($date)->setTimezone($now->getTimezone());
        $daysAgo = (int) round(($now->startOfDay()->getTimestamp() - $day->startOfDay()->getTimestamp()) / 86400);

        return match (true) {
            $daysAgo === 0 => 'Today',
            $daysAgo === 1 => 'Yesterday',
            $day->year === $now->year => $day->format('D, M j'),
            default => $day->format('D, M j, Y'),
        };
    }

    /**
     * The calendar day a date falls on, in the same time zone as dayLabel().
     */
    public static function dayKey(CarbonInterface $date, ?CarbonInterface $now = null): string
    {
        $timezone = ($now ?? CarbonImmutable::now())->getTimezone();

        return CarbonImmutable::instance($date)->setTimezone($timezone)->toDateString();
    }

    /**
     * The date the way `git log` prints it, offset included, since the device
     * time zone isn't visible to PHP.
     */
    public static function gitDate(?CarbonInterface $date): string
    {
        return $date?->format('D M j H:i:s Y O') ?? '';
    }

    public static function shortHash(string $id): string
    {
        return substr(str_replace('-', '', $id), -7);
    }

    public static function count(int $count, string $singular, string $plural): string
    {
        return $count === 1 ? "1 {$singular}" : "{$count} {$plural}";
    }
}
