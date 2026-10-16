<?php

namespace App\Devnull;

use App\Devnull\Data\Commit;
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

    /**
     * An OpenSSH public key with everything after the first 10 characters of the
     * key itself masked. The type and comment stay readable.
     */
    public static function maskedKey(string $openSsh): string
    {
        $parts = explode(' ', trim($openSsh), 3);

        if (count($parts) < 2) {
            return $openSsh;
        }

        $parts[1] = substr($parts[1], 0, 10).str_repeat('•', 12);

        return implode(' ', $parts);
    }

    /**
     * Enough 4pt dashes, with 3pt gaps, to run a dashed line down a commit's
     * row, which is clipped where the row ends. PHP can't know how tall the
     * row is drawn, so this errs long from the text, media and actions in it.
     */
    public static function dashesFor(Commit $commit): int
    {
        $text = $commit->isFolded() ? Commit::PreviewLength : mb_strlen($commit->body);
        $lines = (int) ceil($text / 32) + substr_count($commit->isFolded() ? '' : $commit->body, "\n");
        $height = 110 + $lines * 26
            + ($commit->images === [] ? 0 : 210)
            + ($commit->poll === null ? 0 : 50 + 46 * count($commit->poll->options))
            + ($commit->isQuote() ? 230 : 0)
            + ($commit->linkPreview === null ? 0 : 80);

        return min(250, (int) ceil($height / 7));
    }

    public static function count(int $count, string $singular, string $plural): string
    {
        return $count === 1 ? "1 {$singular}" : "{$count} {$plural}";
    }
}
