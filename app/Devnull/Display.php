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
