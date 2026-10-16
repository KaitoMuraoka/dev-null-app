<?php

namespace App\Devnull\KeyStores;

use App\Devnull\KeyStore;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Native\Mobile\SecureStorageResult;
use Native\Mobile\SecureStorageStatus;

/**
 * A file in the app's storage, encrypted with APP_KEY.
 *
 * For builds without the Secure Storage plugin, so anyone can run the app in a
 * simulator. On a phone NativePHP makes APP_KEY per install, but the file still
 * goes wherever the app's data goes, so this is no Keychain: it keeps the seed
 * out of plain sight and nothing more. KeyStoreChoice only picks it on a real
 * phone when DEVNULL_KEY_STORE asks for it.
 */
final class EncryptedFile implements KeyStore
{
    public function read(string $key): SecureStorageResult
    {
        $encrypted = Storage::disk('local')->get($this->path($key));

        if ($encrypted === null) {
            return new SecureStorageResult(SecureStorageStatus::NotFound);
        }

        try {
            return new SecureStorageResult(SecureStorageStatus::Found, Crypt::decryptString($encrypted));
        } catch (DecryptException) {
            return SecureStorageResult::failure('UNREADABLE', 'The stored key could not be decrypted. Was APP_KEY changed?');
        }
    }

    public function set(string $key, string $value): bool
    {
        return Storage::disk('local')->put($this->path($key), Crypt::encryptString($value));
    }

    public function delete(string $key): void
    {
        Storage::disk('local')->delete($this->path($key));
    }

    private function path(string $key): string
    {
        return "keys/{$key}";
    }
}
