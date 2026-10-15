<?php

namespace App\Devnull;

/**
 * An author's lane on the commit graph, ported from the web's `laneFor()`.
 *
 * The colours themselves are `lane-0` to `lane-5` in the native-ui theme, so
 * `bg-theme-lane-{n}` picks the light or dark lift automatically.
 */
final class Lane
{
    public const int Count = 6;

    public static function for(string $id): int
    {
        $hash = 0;

        foreach (mb_str_split($id) as $character) {
            $hash = ($hash * 31 + self::firstUtf16CodeUnit($character)) & 0xFFFFFFFF;
        }

        return $hash % self::Count;
    }

    /**
     * JavaScript's `charCodeAt(0)` on one code point: the high surrogate above the BMP.
     */
    private static function firstUtf16CodeUnit(string $character): int
    {
        $codePoint = mb_ord($character, 'UTF-8');

        if ($codePoint === false) {
            return 0;
        }

        return $codePoint > 0xFFFF ? 0xD800 + (($codePoint - 0x10000) >> 10) : $codePoint;
    }
}
