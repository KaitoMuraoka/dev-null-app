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

it('says how long ago in words, the way the web says when it last synced', function (string $date, string $expected) {
    $now = CarbonImmutable::parse('2026-10-15T12:00:00Z');

    expect(Display::ago(CarbonImmutable::parse($date), $now))->toBe($expected);
})->with([
    ['2026-10-15T11:59:30Z', 'just now'],
    ['2026-10-15T11:59:00Z', '1 minute ago'],
    ['2026-10-15T11:45:00Z', '15 minutes ago'],
    ['2026-10-15T11:00:00Z', '1 hour ago'],
    ['2026-10-15T09:00:00Z', '3 hours ago'],
    ['2026-10-14T11:00:00Z', 'yesterday'],
    ['2026-10-12T12:00:00Z', '3 days ago'],
    ['2026-07-17T12:00:00Z', '90 days ago'],
]);

it('shortens ids to the last seven characters without dashes', function () {
    expect(Display::shortHash('9b2f6c1e-1d3a-4e5f-8a7b-0c1d2e3f4a5b'))->toBe('e3f4a5b');
});

it("turns a thread elbow's dashes with its corner, one on the middle of the bend and the rest mirrored around it", function () {
    $dashes = Display::elbowDashes(31.5, 64);
    $bend = array_values(array_filter($dashes, fn (array $dash): bool => $dash['rotate'] < 0 && $dash['rotate'] > -90));

    expect($dashes[0])->toBe(['x' => 32.0, 'y' => 5.42, 'rotate' => 0.0])
        // Turned along the bend, down and to the right, and never against it.
        ->and(array_column($bend, 'rotate'))->toBe([-11.58, -45.0, -78.42])
        ->and($bend[1])->toBe(['x' => round(44 - 12 * cos(M_PI / 4), 2), 'y' => round(10 + 12 * sin(M_PI / 4), 2), 'rotate' => -45.0])
        ->and(end($dashes))->toBe(['x' => 62.58, 'y' => 22.0, 'rotate' => -90.0]);

    // A dash and a gap, 7pt, from the middle of one to the middle of the next on the straight runs.
    $across = array_column(array_filter($dashes, fn (array $dash): bool => $dash['rotate'] === -90.0), 'x');

    expect(array_map(fn (float $x, float $next): float => round($next - $x, 2), array_slice($across, 0, -1), array_slice($across, 1)))->toBe([7.0, 7.0]);
});

it("runs a nested reply's elbow from the spine above it to its avatar", function () {
    $dashes = Display::elbowDashes(75.5, 96);

    expect($dashes[0]['x'])->toBe(76.0)
        ->and(end($dashes))->toBe(['x' => 92.58, 'y' => 22.0, 'rotate' => -90.0]);
});

it('prints full dates the way git log does', function () {
    expect(Display::gitDate(CarbonImmutable::parse('2026-10-14T20:13:05Z')))->toBe('Wed Oct 14 20:13:05 2026 +0000')
        ->and(Display::gitDate(null))->toBe('');
});

it('labels days the way the web day markers do', function (string $date, string $expected) {
    $now = CarbonImmutable::parse('2026-10-15T12:00:00Z');

    expect(Display::dayLabel(CarbonImmutable::parse($date), $now))->toBe($expected);
})->with([
    ['2026-10-15T00:00:00Z', 'Today'],
    ['2026-10-15T23:59:59Z', 'Today'],
    ['2026-10-14T23:59:59Z', 'Yesterday'],
    ['2026-10-13T08:00:00Z', 'Tue, Oct 13'],
    ['2025-12-31T08:00:00Z', 'Wed, Dec 31, 2025'],
    // Counted in the app's time zone, whatever offset the API sent.
    ['2026-10-15T01:30:00+02:00', 'Yesterday'],
]);

it('masks a public key after the first 10 characters of the key itself', function () {
    expect(Display::maskedKey('ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIKuhlNtNELRJAJBUbtmUnZKdwgGf devnull-mobile'))
        ->toBe('ssh-ed25519 AAAAC3NzaC•••••••••••• devnull-mobile')
        ->and(Display::maskedKey('ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIKuhlNtNELRJAJBUbtmUnZKdwgGf'))
        ->toBe('ssh-ed25519 AAAAC3NzaC••••••••••••')
        ->and(Display::maskedKey('nothing'))->toBe('nothing');
});
