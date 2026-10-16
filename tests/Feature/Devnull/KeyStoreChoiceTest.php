<?php

use App\Devnull\DeviceKey;
use App\Devnull\Exceptions\KeychainUnavailable;
use App\Devnull\KeyStore;
use App\Devnull\KeyStoreChoice;
use App\Devnull\KeyStores\EncryptedFile;
use App\Devnull\KeyStores\Keychain;
use App\Devnull\KeyStores\NoSecureStorage;
use Illuminate\Support\Facades\Storage;
use Native\Mobile\Providers\SecureStorageServiceProvider;
use Native\Mobile\Testing\Native;

it('picks where the key is kept from the build and the device', function (?string $configured, bool $hasSecureStorage, bool $onRealPhone, string $expected) {
    expect((new KeyStoreChoice($configured, $hasSecureStorage, $onRealPhone))->store())->toBe($expected);
})->with([
    'plugin, simulator' => [null, true, false, Keychain::class],
    'plugin, real phone' => [null, true, true, Keychain::class],
    'no plugin, simulator' => [null, false, false, EncryptedFile::class],
    'no plugin, real phone' => [null, false, true, NoSecureStorage::class],
    'file asked for, even on a real phone with the plugin' => ['file', true, true, EncryptedFile::class],
    'keychain asked for, even without the plugin' => ['keychain', false, false, Keychain::class],
]);

it('uses the Keychain in a build with the Secure Storage plugin, without asking the phone', function () {
    config(['devnull.key_store' => null]);
    $bridge = Native::fakeBridge()->respondTo('Device.GetInfo', ['info' => json_encode(['isVirtual' => false])]);

    expect(app(KeyStore::class))->toBeInstanceOf(Keychain::class);
    $bridge->assertNotCalled('Device.GetInfo');
})->skip(! class_exists(SecureStorageServiceProvider::class), 'needs the Secure Storage plugin installed');

it('ignores a store it does not know', function () {
    config(['devnull.key_store' => 'cloud']);

    expect(KeyStoreChoice::here()->store())->toBe(class_exists(SecureStorageServiceProvider::class) ? Keychain::class : EncryptedFile::class);
});

it('asks NativePHP whether this is a real phone', function (?array $info, bool $expected) {
    $bridge = Native::fakeBridge();

    if ($info !== null) {
        $bridge->respondTo('Device.GetInfo', ['info' => json_encode($info)]);
    }

    expect(KeyStoreChoice::runningOnRealPhone())->toBe($expected);
})->with([
    'iPhone' => [['platform' => 'ios', 'isVirtual' => false], true],
    'simulator' => [['platform' => 'ios', 'isVirtual' => true], false],
    'no answer' => [null, false],
    'an answer without isVirtual' => [['platform' => 'ios'], false],
]);

it('is off a phone in tests, so the file store stays usable here', function () {
    expect(KeyStoreChoice::runningOnRealPhone())->toBeFalse();
});

it('refuses to make or keep a key on a real phone without the plugin', function () {
    Storage::fake('local');
    $deviceKey = new DeviceKey(new NoSecureStorage);

    expect(fn () => $deviceKey->current())->toThrow(KeychainUnavailable::class, NoSecureStorage::Message)
        ->and(fn () => $deviceKey->existing())->toThrow(KeychainUnavailable::class, NoSecureStorage::Message)
        ->and(Storage::disk('local')->allFiles())->toBe([]);

    expect(NoSecureStorage::Message)->toStartWith('fatal: ');
});

it('shows onboarding the refusal instead of a key', function () {
    app()->bind(KeyStore::class, NoSecureStorage::class);
    $bridge = Native::fakeBridge();

    Native::visit('/onboarding')
        ->assertSee(NoSecureStorage::Message)
        ->assertSet('publicKey', '');

    $bridge->assertNotCalled('SecureStorage.Set');
});
