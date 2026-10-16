<?php

use App\Devnull\Account;
use App\Devnull\DeviceKey;
use App\Devnull\Display;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Testing\Native;

beforeEach(function () {
    config(['devnull.url' => 'http://devnull.test']);
});

it('shows this device key and where it talks to, pushed onto the Profile tab', function () {
    signIn();

    Native::visit('/profile/device')
        ->assertNavTitle('This device')
        ->assertTabActive('Profile')
        ->assertSee('ssh-ed25519')
        ->assertSee('SHA256:lbmsoA0yIEcEiVDRnMWuzm+nV+3ZEEpVIURqFoeSspg')
        ->assertSee(Display::maskedKey(testKey()->openSsh()))
        ->assertDontSee(testKey()->openSsh())
        ->assertSee('http://devnull.test')
        ->assertSee('Share public key')
        ->assertSee('Forget this device');
});

it('shares the public key and opens your devices on dev-null.sh', function () {
    signIn();

    Native::visit('/profile/device')
        ->tap('share-key')
        ->assertNativeCalled('Share.File', fn (array $params): bool => $params['message'] === testKey()->openSsh())
        ->tap('devices')
        ->assertNativeCalled('Browser.OpenInApp', fn (array $params): bool => $params['url'] === 'http://devnull.test/settings/devices');
});

it('forgets the device only after a confirm', function () {
    signIn();

    $screen = Native::visit('/profile/device')
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
