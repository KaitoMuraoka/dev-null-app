<?php

namespace App\NativeComponents;

use App\Devnull\Account;
use App\Devnull\DeviceKey;
use App\Devnull\Exceptions\KeychainUnavailable;
use App\NativeComponents\Concerns\TalksToDevnull;
use Illuminate\View\View;
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Facades\Browser;
use Native\Mobile\Facades\Dialog;
use Native\Mobile\Facades\Share;

/**
 * This device's key and where it talks to, reached from your own profile.
 */
class ThisDevice extends NativeComponent
{
    use TalksToDevnull;

    public const string ForgetAlert = 'forget-device';

    public string $publicKey = '';

    public string $fingerprint = '';

    public function mount(): void
    {
        try {
            $key = app(DeviceKey::class)->current();
            $this->publicKey = $key->openSsh();
            $this->fingerprint = $key->fingerprint();
        } catch (KeychainUnavailable $exception) {
            $this->error = $exception->getMessage();
        }
    }

    public function navTitle(): string
    {
        return 'This device';
    }

    public function shareKey(): void
    {
        Share::file('', $this->publicKey, '');
    }

    public function openGitHubKeys(): void
    {
        Browser::inApp('https://github.com/settings/keys');
    }

    public function confirmForget(): void
    {
        Dialog::alert(
            'Forget this device?',
            'This deletes the device key from the Keychain and signs you out. The public key stays on GitHub until you remove it there. Connecting again makes a new key.',
            [
                ['label' => 'Cancel', 'style' => 'cancel'],
                ['label' => 'Forget', 'style' => 'destructive'],
            ],
        )->id(self::ForgetAlert);
    }

    #[On(ButtonPressed::class)]
    public function onAlertButton(string $label = '', ?string $id = null): void
    {
        if ($id === self::ForgetAlert && $label === 'Forget') {
            $this->forget();
        }
    }

    public function forget(): void
    {
        app(DeviceKey::class)->forget();
        app(Account::class)->forget();

        $this->replace('/onboarding');
    }

    public function render(): View
    {
        return view('native.this-device', ['remote' => config('devnull.url')]);
    }
}
