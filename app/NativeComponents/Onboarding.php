<?php

namespace App\NativeComponents;

use App\Devnull\Account;
use App\Devnull\Client;
use App\Devnull\DeviceKey;
use App\Devnull\Exceptions\ApiException;
use App\Devnull\Exceptions\KeychainUnavailable;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Facades\Browser;
use Native\Mobile\Facades\Share;

/**
 * This phone has its own SSH key. Add it to GitHub once and devnull knows who you are.
 */
class Onboarding extends NativeComponent
{
    public const string GitHubNewKeyUrl = 'https://github.com/settings/ssh/new';

    public string $publicKey = '';

    public string $fingerprint = '';

    public string $username = '';

    public ?string $error = null;

    public ?string $notice = null;

    public function mount(DeviceKey $deviceKey): void
    {
        $this->notice = $this->data('message');

        try {
            $key = $deviceKey->current();
        } catch (KeychainUnavailable $exception) {
            $this->error = $exception->getMessage();

            return;
        }

        $this->publicKey = $key->openSsh();
        $this->fingerprint = $key->fingerprint();

        if (config('app.debug')) {
            Log::info('devnull device key', ['public_key' => $this->publicKey, 'fingerprint' => $this->fingerprint]);
        }
    }

    public function shareKey(): void
    {
        Share::file('', $this->publicKey, '');
    }

    public function openGitHub(): void
    {
        Browser::inApp(self::GitHubNewKeyUrl);
    }

    public function connect(): void
    {
        $username = ltrim(trim($this->username), '@');

        if ($username === '' || $this->publicKey === '') {
            return;
        }

        try {
            $user = app(Client::class)->syncKey($username);
        } catch (ApiException $exception) {
            $this->error = $exception->firstError();

            return;
        } catch (KeychainUnavailable $exception) {
            $this->error = $exception->getMessage();

            return;
        }

        app(Account::class)->remember($user);
        $this->replace('/log');
    }

    public function render(): View
    {
        return view('native.onboarding');
    }
}
