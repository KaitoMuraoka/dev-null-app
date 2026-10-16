<?php

namespace App\Devnull\KeyStores;

use App\Devnull\Exceptions\KeychainUnavailable;
use App\Devnull\KeyStore;
use Native\Mobile\SecureStorageResult;

/**
 * Stands in on a real phone built without the Secure Storage plugin.
 *
 * It keeps nothing and says why, so the app never makes a key it would hold
 * in a weaker place than the Keychain, or get devnull to approve from there.
 */
final class NoSecureStorage implements KeyStore
{
    public const string Message = "fatal: this build has no Secure Storage plugin, so it won't keep a key on a real phone. Build it with the plugin, or run it in a simulator.";

    public function read(string $key): SecureStorageResult
    {
        throw new KeychainUnavailable(self::Message);
    }

    public function set(string $key, string $value): bool
    {
        throw new KeychainUnavailable(self::Message);
    }

    public function delete(string $key): void
    {
        //
    }
}
