<?php

use App\Devnull\Account;
use App\Devnull\Client;
use App\Devnull\DeviceKey;
use App\Devnull\Display;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Testing\Native;

beforeEach(function () {
    config(['devnull.url' => 'http://devnull.test']);
});

it('shows this phone\'s fingerprint, pushed onto the Profile tab', function () {
    signIn();

    Native::visit('/profile/device')
        ->assertNavTitle('This device')
        ->assertTabActive('Profile')
        ->assertSee('SHA256:lbmsoA0yIEcEiVDRnMWuzm+nV+3ZEEpVIURqFoeSspg')
        ->assertSee('Your devices on dev-null.sh')
        ->assertSee('Forget this device')
        ->assertDontSee('ssh-ed25519')
        ->assertDontSee(Display::maskedKey(testKey()->openSsh()))
        ->assertDontSee('Share public key')
        ->assertDontSee('Keychain');
});

it('shows where the app talks to only when it is not dev-null.sh', function (string $url, ?string $shown) {
    config(['devnull.url' => $url]);
    signIn();

    $screen = Native::visit('/profile/device');
    $remote = findNode($screen->tree(), fn (array $node): bool => ($node['ref'] ?? null) === 'remote');

    if ($shown === null) {
        expect($remote)->toBeNull();
        $screen->assertDontSee('remote')->assertDontSee('https://');
    } else {
        expect($remote)->not->toBeNull();
        $screen->assertSee('remote')->assertSee($shown);
    }
})->with([
    'production' => [Client::ProductionUrl, null],
    'production with a trailing slash' => [Client::ProductionUrl.'/', null],
    'a local copy' => ['http://devnull.test', 'http://devnull.test'],
]);

it('opens your devices on dev-null.sh', function () {
    signIn();

    Native::visit('/profile/device')
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
