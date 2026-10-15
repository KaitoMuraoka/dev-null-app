<?php

use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;
use Native\Mobile\Testing\TestableComponent;

beforeEach(function () {
    config(['devnull.url' => 'http://devnull.test']);
    signIn();
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
    Http::fake(['devnull.test/*' => Http::response(pageJson([]))]);

    // Lucide's GitCommitVertical, Bell and UserRound. SF Symbols has no vertical commit.
    expect(tabIcons(Native::visit('/log', platform: 'ios')))->toBe([
        'Log' => ['circle.and.line.horizontal', null],
        'Notifications' => ['bell', null],
        'Profile' => ['person', null],
    ]);
});

it('uses the outlined Material icons on Android to match the web strokes', function () {
    Http::fake(['devnull.test/*' => Http::response(pageJson([]))]);

    expect(tabIcons(Native::visit('/log', platform: 'android')))->toBe([
        'Log' => ['commit', 'outlined'],
        'Notifications' => ['notifications', 'outlined'],
        'Profile' => ['person', 'outlined'],
    ]);
});

it('pushes commits and profiles onto the tab they were opened from, so they slide in', function () {
    // Native tabs only push a URI under the tab's own; anything else swaps the whole screen without a slide.
    Http::fake([
        'devnull.test/api/v1/log/following' => Http::response(pageJson([commitJson(['id' => 'c1', 'user' => userJson(['id' => 'u2', 'username' => 'ada'])])])),
        'devnull.test/api/v1/commits/c1' => Http::response(['data' => commitJson(['id' => 'c1', 'user' => userJson(['id' => 'u2', 'username' => 'ada'])]), 'ancestors' => []]),
        'devnull.test/*' => Http::response(pageJson([])),
    ]);

    Native::visit('/log')
        ->tap('commit-c1')
        ->assertNavigatedTo('/log/commits/c1')
        ->follow()
        ->assertTabActive('Log')
        ->assertNavTitle('Commit')
        ->call('openProfile', 'ada')
        ->assertNavigatedTo('/log/users/ada');

    Native::visit('/log/commits/c1')->call('openCommit', 'c0')->assertNavigatedTo('/log/commits/c0');

    Native::visit('/profile/commits/c1')->call('openProfile', 'ada')->assertNavigatedTo('/profile/users/ada');
    Native::visit('/notifications/commits/c1')->call('openCommit', 'c0')->assertNavigatedTo('/notifications/commits/c0');
});

