<?php

use App\Devnull\Account;
use App\Devnull\DeviceKey;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Testing\Native;

beforeEach(function () {
    config(['devnull.url' => 'http://devnull.test']);
});

it('shows this device key and where it talks to, in stack chrome', function () {
    signIn();

    Native::visit('/device')
        ->assertNavTitle('This device')
        ->assertMissingElement('native_root_tabs')
        ->assertSee('ssh-ed25519')
        ->assertSee('SHA256:lbmsoA0yIEcEiVDRnMWuzm+nV+3ZEEpVIURqFoeSspg')
        ->assertSee(testKey()->openSsh())
        ->assertSee('http://devnull.test')
        ->assertSee('Share public key')
        ->assertSee('Forget this device');
});

it('shares the public key and opens your keys on GitHub', function () {
    signIn();

    Native::visit('/device')
        ->tap('share-key')
        ->assertNativeCalled('Share.File', fn (array $params): bool => $params['message'] === testKey()->openSsh())
        ->tap('github-keys')
        ->assertNativeCalled('Browser.OpenInApp', fn (array $params): bool => $params['url'] === 'https://github.com/settings/keys');
});

it('forgets the device only after a confirm', function () {
    signIn();

    $screen = Native::visit('/device')
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
