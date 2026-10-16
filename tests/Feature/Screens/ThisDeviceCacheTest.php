<?php

use Illuminate\Support\Facades\Http;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Platform;
use Native\Mobile\Testing\Native;

beforeEach(function () {
    config(['devnull.url' => 'http://devnull.test']);
    signIn()->respondTo('NativephpSocialAuth.AppleSignIn', ['pending' => true]);
});

afterEach(fn () => Platform::set(null));

function serveMe(bool $appleLinked): void
{
    serveApi([
        'GET /api/v1/me' => [['data' => userWithCountsJson(), 'unread_notifications_count' => 0, 'apple_linked' => $appleLinked]],
        'DELETE /api/v1/me/apple' => [['apple_linked' => false]],
    ]);
}

it('shows the Apple link as devnull last said, with no request, then asks again behind it', function () {
    holdRefreshes();
    keep('me', ['data' => userWithCountsJson(), 'unread_notifications_count' => 0, 'apple_linked' => true]);
    serveMe(appleLinked: false);

    $screen = Native::visit('/profile/device', platform: 'ios')
        ->assertSet('appleLinked', true)
        ->assertSee('Unlink Apple ID');

    expect(count(Http::recorded()))->toBe(0)
        ->and(showsLoading($screen->tree()))->toBeFalse();

    finishRefreshes($screen)->assertSet('appleLinked', false)->assertSee('Sign in with Apple');
});

it('keeps the Apple link as it changes, so the screen opens on it next time', function () {
    keep('me', ['data' => userWithCountsJson(), 'unread_notifications_count' => 0, 'apple_linked' => true]);
    serveMe(appleLinked: true);

    Native::visit('/profile/device', platform: 'ios')
        ->call('onAlertButton', 'Unlink', 'unlink-apple')
        ->assertSet('appleLinked', false);

    expect(kept('me')['apple_linked'])->toBeFalse();

    holdRefreshes();

    Native::visit('/profile/device', platform: 'ios')->assertSet('appleLinked', false);
});

it('keeps the status and says it could not refresh when offline', function () {
    holdRefreshes();
    keep('me', ['data' => userWithCountsJson(), 'unread_notifications_count' => 0, 'apple_linked' => true]);
    fakeOffline();

    finishRefreshes(Native::visit('/profile/device', platform: 'ios'))
        ->assertSet('appleLinked', true)
        ->assertSee("warning: couldn't refresh")
        ->assertSet('error', null);
});

it('empties the cache when you sign out or delete your account', function (string $label, string $id) {
    keep('log/following', pageJson([commitJson()]));
    keep('me', ['data' => userWithCountsJson()]);
    serveApi(['DELETE /api/v1/me' => [null, 204]]);

    Native::visit('/profile/device')
        ->emitNative(ButtonPressed::class, ['index' => 1, 'label' => $label, 'id' => $id])
        ->assertReplacedWith('/onboarding');

    signIn();

    expect(kept('log/following'))->toBeNull()
        ->and(kept('me'))->toBeNull();
})->with([
    'sign out' => ['Sign out', 'sign-out'],
    'delete account' => ['Delete', 'delete-account'],
]);

it('keeps the cache when devnull cannot delete your account', function () {
    keep('log/following', pageJson([commitJson()]));
    serveApi(['DELETE /api/v1/me' => [['message' => 'Server Error'], 500]]);

    Native::visit('/profile/device')
        ->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Delete', 'id' => 'delete-account'])
        ->assertNoNavigation();

    expect(kept('log/following'))->not->toBeNull();
});
