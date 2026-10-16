<?php

namespace App\Devnull;

use App\Devnull\KeyStores\EncryptedFile;
use App\Devnull\KeyStores\Keychain;
use App\Devnull\KeyStores\NoSecureStorage;
use Native\Mobile\Facades\Device;
use Native\Mobile\Providers\SecureStorageServiceProvider;

/**
 * Where this phone's key is kept, worked out on the phone each time it's asked.
 *
 * The Keychain whenever the Secure Storage plugin is in the build. Without it,
 * an encrypted file, but only in a simulator or off a phone: on a real phone
 * the app refuses rather than quietly keep the key somewhere weaker.
 * DEVNULL_KEY_STORE picks one outright.
 */
final readonly class KeyStoreChoice
{
    public function __construct(
        private ?string $configured,
        private bool $hasSecureStorage,
        private bool $onRealPhone,
    ) {}

    /**
     * The choice for this build on this device. The plugin's service provider
     * is only there when the plugin was installed for the build, and only a
     * build without it asks the phone whether it's a simulator.
     */
    public static function here(): self
    {
        $configured = config('devnull.key_store');
        $configured = in_array($configured, ['keychain', 'file'], true) ? $configured : null;
        $hasSecureStorage = class_exists(SecureStorageServiceProvider::class);

        return new self(
            $configured,
            $hasSecureStorage,
            $configured === null && ! $hasSecureStorage && self::runningOnRealPhone(),
        );
    }

    /**
     * Whether NativePHP says this is a real phone, not a simulator or an
     * emulator. Off a phone, in tests or artisan, there's nothing to ask.
     */
    public static function runningOnRealPhone(): bool
    {
        $info = json_decode((string) Device::getInfo(), true);

        return is_array($info) && ($info['isVirtual'] ?? null) === false;
    }

    /**
     * @return class-string<KeyStore>
     */
    public function store(): string
    {
        return match (true) {
            $this->configured === 'keychain' => Keychain::class,
            $this->configured === 'file' => EncryptedFile::class,
            $this->hasSecureStorage => Keychain::class,
            $this->onRealPhone => NoSecureStorage::class,
            default => EncryptedFile::class,
        };
    }
}
