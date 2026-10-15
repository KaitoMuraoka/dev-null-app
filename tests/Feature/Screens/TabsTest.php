<?php

use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;
use Native\Mobile\Testing\TestableComponent;

beforeEach(function () {
    config(['devnull.url' => 'http://devnull.test']);
    signIn();
    Http::fake(['devnull.test/*' => Http::response(pageJson([]))]);
});

/**
 * Each tab as label => [icon, Material variant].
 *
 * @return array<string, array{0: ?string, 1: ?string}>
 */
function tabIcons(TestableComponent $screen): array
{
    return collect(findNodes($screen->tree(), fn (array $node): bool => ($node['type'] ?? null) === 'bottom_nav_item'))
        ->mapWithKeys(fn (array $tab): array => [$tab['props']['label'] => [$tab['props']['icon'] ?? null, $tab['props']['material_variant'] ?? null]])
        ->all();
}

it('has Log, Notifications and Profile tabs with the nearest SF Symbols to the web phone nav', function () {
    // Lucide's GitCommitVertical, Bell and UserRound. SF Symbols has no vertical commit.
    expect(tabIcons(Native::visit('/log', platform: 'ios')))->toBe([
        'Log' => ['circle.and.line.horizontal', null],
        'Notifications' => ['bell', null],
        'Profile' => ['person', null],
    ]);
});

it('uses the outlined Material icons on Android to match the web strokes', function () {
    expect(tabIcons(Native::visit('/log', platform: 'android')))->toBe([
        'Log' => ['commit', 'outlined'],
        'Notifications' => ['notifications', 'outlined'],
        'Profile' => ['person', 'outlined'],
    ]);
});
