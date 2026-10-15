<?php

use App\Devnull\Account;
use App\Devnull\DeviceKey;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Testing\Native;

beforeEach(function () {
    config(['devnull.url' => 'http://devnull.test']);
});

it('shows you, this device key and where it talks to', function () {
    signIn();
    fakeApi(['GET /api/v1/me' => [['data' => userJson(['name' => 'Local Developer'])]]]);

    Native::visit('/me')
        ->assertNavTitle('Me')
        ->assertTabActive('Me')
        ->assertSee('Local Developer')
        ->assertSee('@local_dev')
        ->assertSee('SHA256:lbmsoA0yIEcEiVDRnMWuzm+nV+3ZEEpVIURqFoeSspg')
        ->assertSee(testKey()->openSsh())
        ->assertSee('http://devnull.test');

    expect(app(Account::class)->user()?->name)->toBe('Local Developer');
});

it('opens your profile and shares the public key', function () {
    signIn();
    fakeApi(['GET /api/v1/me' => [['data' => userJson()]]]);

    Native::visit('/me')
        ->tap('share-key')
        ->assertNativeCalled('Share.File', fn (array $params): bool => $params['message'] === testKey()->openSsh())
        ->tap('github-keys')
        ->assertNativeCalled('Browser.OpenInApp', fn (array $params): bool => $params['url'] === 'https://github.com/settings/keys')
        ->tap('my-profile')
        ->assertNavigatedTo('/users/local_dev');
});

it('forgets the device only after a confirm', function () {
    $bridge = signIn();
    fakeApi(['GET /api/v1/me' => [['data' => userJson()]]]);

    $screen = Native::visit('/me')
        ->tap('forget-device')
        ->assertNativeCalled('Dialog.Alert', fn (array $params): bool => $params['title'] === 'Forget this device?')
        ->emitNative(ButtonPressed::class, ['index' => 0, 'label' => 'Cancel', 'id' => 'forget-device'])
        ->assertNoNavigation()
        ->assertNativeNotCalled('SecureStorage.Delete');

    $screen->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Forget', 'id' => 'forget-device'])
        ->assertNativeCalled('SecureStorage.Delete', fn (array $params): bool => $params['key'] === DeviceKey::StorageKey)
        ->assertReplacedWith('/onboarding');

    expect(app(Account::class)->user())->toBeNull();

    // A new key is made next time, so the old one on GitHub no longer works here.
    expect(app(DeviceKey::class)->current()->openSsh())->not->toBe(testKey()->openSsh());
});
