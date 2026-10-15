<?php

use App\Devnull\Display;
use Carbon\CarbonImmutable;

it('prints relative times the way the web does', function (string $date, string $expected) {
    $now = CarbonImmutable::parse('2026-10-15T12:00:00Z');

    expect(Display::relativeTime(CarbonImmutable::parse($date), $now))->toBe($expected);
})->with([
    ['2026-10-15T11:59:30Z', 'now'],
    ['2026-10-15T11:45:00Z', '15m'],
    ['2026-10-15T09:00:00Z', '3h'],
    ['2026-10-12T12:00:00Z', '3d'],
    ['2026-09-01T12:00:00Z', 'Sep 1'],
    ['2025-12-31T12:00:00Z', 'Dec 31, 2025'],
]);

it('shortens ids to the last seven characters without dashes', function () {
    expect(Display::shortHash('9b2f6c1e-1d3a-4e5f-8a7b-0c1d2e3f4a5b'))->toBe('e3f4a5b');
});

it('prints full dates the way git log does', function () {
    expect(Display::gitDate(CarbonImmutable::parse('2026-10-14T20:13:05Z')))->toBe('Wed Oct 14 20:13:05 2026 +0000')
        ->and(Display::gitDate(null))->toBe('');
});
