<?php

use App\Devnull\Account;
use App\Devnull\Client;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;
use Native\Mobile\Testing\TestableComponent;

beforeEach(function () {
    config(['devnull.url' => 'http://devnull.test']);
    signIn();
});

/**
 * What VoiceOver reads for each colour, Automatic first.
 *
 * @return list<string>
 */
function laneLabels(TestableComponent $screen): array
{
    return array_map(fn (array $node): string => $node['props']['a11y_label'], nodeByRef($screen, 'lanes')['children']);
}

/**
 * The light colour of a lane in the theme.
 */
function laneColour(int $lane): string
{
    return strtolower((string) config("native-ui.theme.light.lane-{$lane}"));
}

/**
 * The colour the preview draws the dot before your handle in.
 */
function previewColour(TestableComponent $screen): string
{
    return strtolower(nodeByRef($screen, 'preview-dot')['style']['bg_color']);
}

/**
 * The tab bar's accent, the colour the tab you're on is picked out in.
 */
function tabAccent(TestableComponent $screen): string
{
    return strtolower(findNode($screen->tree(), fn (array $node): bool => isset($node['props']['active_color']))['props']['active_color']);
}

/**
 * The `PUT /me/lane` requests sent, as their bodies.
 *
 * @return list<string>
 */
function laneSaves(): array
{
    return collect(Http::recorded())
        ->filter(fn (array $sent): bool => $sent[0]->method() === 'PUT' && str_ends_with($sent[0]->url(), Client::LanePath))
        ->map(fn (array $sent): string => $sent[0]->body())
        ->values()
        ->all();
}

it('offers Automatic and the six lanes by name, with the one you have marked', function () {
    serveApi(['GET /api/v1/me' => [currentUserJson(['lane' => 2, 'preferred_lane' => 2, 'automatic_lane' => 4])]]);

    $screen = Native::visit('/profile/device')->assertSet('chosenLane', 2);

    expect(laneLabels($screen))->toBe(['Automatic (rose)', 'Orange', 'Green', 'Violet, selected', 'Teal', 'Rose', 'Ochre'])
        ->and(previewColour($screen))->toBe(laneColour(2))
        ->and(nodeByRef($screen, 'save-lane')['props'])->toMatchArray(['label' => 'Save color', 'disabled' => true]);

    // Each colour is drawn in its lane, and the one you have wears a ring of it.
    $violet = nodeByRef($screen, 'lane-2');
    $ring = findNode($violet, fn (array $node): bool => ($node['type'] ?? null) === 'stack');

    expect(strtolower($ring['style']['border_color']))->toBe(laneColour(2))
        ->and(strtolower(findNode($ring, fn (array $node): bool => isset($node['style']['bg_color']))['style']['bg_color']))->toBe(laneColour(2))
        ->and(pressFeedbackInScroller($screen))->toBe([]);
});

it('marks Automatic when you have not picked a colour, in the lane it gives you', function () {
    serveApi(['GET /api/v1/me' => [currentUserJson(['lane' => 4, 'preferred_lane' => null, 'automatic_lane' => 4])]]);

    $screen = Native::visit('/profile/device')->assertSet('chosenLane', null);

    expect(laneLabels($screen)[0])->toBe('Automatic (rose), selected')
        ->and(previewColour($screen))->toBe(laneColour(4));
});

it('previews a colour when you tap it, and saves nothing until you say so', function () {
    serveApi(['GET /api/v1/me' => [currentUserJson(['lane' => 3, 'preferred_lane' => null, 'automatic_lane' => 3])]]);

    $screen = Native::visit('/profile/device')
        ->tap('lane-1')
        ->assertSet('chosenLane', 1);

    expect(laneLabels($screen)[2])->toBe('Green, selected')
        ->and(laneLabels($screen)[0])->toBe('Automatic (teal)')
        ->and(previewColour($screen))->toBe(laneColour(1))
        ->and(nodeByRef($screen, 'save-lane')['props'])->not->toHaveKey('disabled')
        // Only a saved colour changes your ring and the tab bar.
        ->and(tabAccent($screen))->toBe(laneColour(3))
        ->and(app(Account::class)->user()?->lane())->toBe(3)
        ->and(laneSaves())->toBe([]);

    // Back to the colour you have, there's nothing to save.
    $screen->tap('lane-auto')->assertSet('chosenLane', null);

    expect(nodeByRef($screen, 'save-lane')['props']['disabled'] ?? false)->toBeTrue();

    $screen->call('saveLane')->call('chooseLane', '9')->assertSet('chosenLane', null);

    expect(laneSaves())->toBe([]);
});

it('saves the colour, then the tab bar and your profile take it at once', function () {
    $bridge = holdRefreshes();
    keep('me', currentUserJson(['lane' => 3, 'preferred_lane' => null, 'automatic_lane' => 3]));
    keep('users/local_dev', ['data' => userWithCountsJson(['lane' => 3]), 'is_following' => false]);
    keep('users/local_dev/commits', pageJson([commitJson()]));
    serveApi([
        'GET /api/v1/me' => [currentUserJson(['lane' => 3, 'preferred_lane' => null, 'automatic_lane' => 3])],
        'PUT /api/v1/me/lane' => [currentUserJson(['lane' => 5, 'preferred_lane' => 5, 'automatic_lane' => 3])],
    ]);

    $screen = Native::visit('/profile/device')->tap('lane-5')->tap('save-lane');

    expect(nodeByRef($screen, 'save-lane')['props'])->toMatchArray(['loading' => true, 'disabled' => true])
        ->and(tabAccent($screen))->toBe(laneColour(3));

    finishRefreshes($screen)
        ->assertSet('savingLane', false)
        ->assertSet('laneError', null)
        ->assertSet('chosenLane', 5);

    expect(laneSaves())->toBe(['{"lane":5}'])
        ->and(nodeByRef($screen, 'save-lane')['props']['disabled'] ?? false)->toBeTrue()
        ->and(app(Account::class)->user()?->lane())->toBe(5)
        ->and(tabAccent($screen))->toBe(laneColour(5))
        ->and(kept('me')['data']['preferred_lane'])->toBe(5)
        ->and(kept('users/local_dev')['data']['lane'])->toBe(5)
        ->and(kept('users/local_dev/commits')['data'][0]['author']['lane'])->toBe(5);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
        && $request->url() === 'http://devnull.test/api/v1/'.Client::LanePath
        && $request->hasHeader('X-SSH-Signature'));
    $bridge->assertNotCalled('Dialog.Toast');

    // Your profile wears it as soon as you go back to it.
    $profile = Native::visit('/profile');
    $ring = findNode($profile->tree(), fn (array $node): bool => ($node['type'] ?? null) === 'stack' && (float) ($node['layout']['width'] ?? 0) === 92.0);

    expect(strtolower($ring['style']['bg_color']))->toBe(laneColour(5))
        ->and(railNode($profile, 'commit-0199e5c4-1111-7000-8000-00000000c0de'))->toBe('avatar')
        ->and(tabAccent($profile))->toBe(laneColour(5));
});

it('goes back to Automatic, saved as no lane', function () {
    serveApi([
        'GET /api/v1/me' => [currentUserJson(['lane' => 1, 'preferred_lane' => 1, 'automatic_lane' => 4])],
        'PUT /api/v1/me/lane' => [currentUserJson(['lane' => 4, 'preferred_lane' => null, 'automatic_lane' => 4])],
    ]);

    $screen = Native::visit('/profile/device')
        ->tap('lane-auto')
        ->tap('save-lane')
        ->assertSet('chosenLane', null);

    expect(laneSaves())->toBe(['{"lane":null}'])
        ->and(laneLabels($screen)[0])->toBe('Automatic (rose), selected')
        ->and(app(Account::class)->user()?->lane())->toBe(4)
        ->and(tabAccent($screen))->toBe(laneColour(4));
});

it('says in the section why it could not save, and keeps the colour picked', function () {
    serveApi([
        'GET /api/v1/me' => [currentUserJson(['lane' => 3, 'preferred_lane' => null, 'automatic_lane' => 3])],
        'PUT /api/v1/me/lane' => [['message' => 'The lane field must be between 0 and 5.', 'errors' => ['lane' => ['The lane field must be between 0 and 5.']]], 422],
    ]);

    $screen = Native::visit('/profile/device')
        ->tap('lane-0')
        ->tap('save-lane')
        ->assertSet('savingLane', false)
        ->assertSet('laneError', 'The lane field must be between 0 and 5.')
        ->assertSet('chosenLane', 0)
        ->assertSet('error', null);

    expect(nodeByRef($screen, 'lane-error')['props']['text'])->toBe('The lane field must be between 0 and 5.')
        ->and(app(Account::class)->user()?->lane())->toBe(3)
        ->and(tabAccent($screen))->toBe(laneColour(3));

    // Picking again clears it.
    $screen->tap('lane-1')->assertSet('laneError', null);
});

it('keeps a colour you picked when devnull answers again, and otherwise follows it', function () {
    holdRefreshes();
    keep('me', currentUserJson(['lane' => 3, 'preferred_lane' => null, 'automatic_lane' => 3]));
    serveApi(['GET /api/v1/me' => [currentUserJson(['lane' => 2, 'preferred_lane' => 2, 'automatic_lane' => 3])]]);

    $picked = Native::visit('/profile/device')->tap('lane-0');

    finishRefreshes($picked)->assertSet('chosenLane', 0);

    holdRefreshes();
    keep('me', currentUserJson(['lane' => 3, 'preferred_lane' => null, 'automatic_lane' => 3]));

    finishRefreshes(Native::visit('/profile/device'))->assertSet('chosenLane', 2);
});
