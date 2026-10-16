<?php

namespace App\NativeComponents;

use App\Devnull\Account;
use App\Devnull\AppleSignIn;
use App\Devnull\Client;
use App\Devnull\DeviceKey;
use App\Devnull\Exceptions\KeychainUnavailable;
use App\NativeComponents\Concerns\SignsInWithApple;
use App\NativeComponents\Concerns\TalksToDevnull;
use Illuminate\View\View;
use Native\Mobile\Attributes\Lazy;
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Facades\Browser;
use Native\Mobile\Facades\Dialog;
use Native\Mobile\Facades\Share;
use SensitiveParameter;

/**
 * This device's key and where it talks to, reached from your own profile.
 *
 * On iOS it also links an Apple ID to your account, as a second way in on a
 * new phone. The account stays the GitHub one: Apple only signs you into it.
 */
#[Lazy]
class ThisDevice extends NativeComponent
{
    use SignsInWithApple;
    use TalksToDevnull;

    public const string ForgetAlert = 'forget-device';

    public const string UnlinkAppleAlert = 'unlink-apple';

    public string $publicKey = '';

    public string $fingerprint = '';

    /**
     * Whether devnull has an Apple ID linked to you, or null until it says.
     */
    public ?bool $appleLinked = null;

    public function mount(): void
    {
        try {
            $key = app(DeviceKey::class)->current();
            $this->publicKey = $key->openSsh();
            $this->fingerprint = $key->fingerprint();
        } catch (KeychainUnavailable $exception) {
            $this->error = $exception->getMessage();

            return;
        }

        if (AppleSignIn::isAvailable()) {
            $this->appleLinked = $this->attempt(fn (Client $devnull): bool => $devnull->me()->appleLinked);
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

    /**
     * The devices page on dev-null.sh, where this phone is listed once approved.
     */
    public function openDevices(): void
    {
        Browser::inApp(rtrim((string) config('devnull.url'), '/').'/settings/devices');
    }

    public function confirmForget(): void
    {
        Dialog::alert(
            'Forget this device?',
            'This deletes the device key from the Keychain and signs you out. The phone stays listed on dev-null.sh until you remove it there. Signing in again makes a new key.',
            [
                ['label' => 'Cancel', 'style' => 'cancel'],
                ['label' => 'Forget', 'style' => 'destructive'],
            ],
        )->id(self::ForgetAlert);
    }

    /**
     * Opens Apple's sheet. Its answer arrives as an event (see SignsInWithApple).
     */
    public function linkApple(): void
    {
        if (! AppleSignIn::isAvailable()) {
            return;
        }

        $this->error = null;
        $this->startAppleSignIn();
    }

    public function confirmUnlinkApple(): void
    {
        Dialog::alert(
            'Unlink your Apple ID?',
            'Sign in with Apple stops working for your account until you link it again. Your GitHub sign-in and this phone stay as they are.',
            [
                ['label' => 'Cancel', 'style' => 'cancel'],
                ['label' => 'Unlink', 'style' => 'destructive'],
            ],
        )->id(self::UnlinkAppleAlert);
    }

    public function unlinkApple(): void
    {
        if (! AppleSignIn::isAvailable()) {
            return;
        }

        $linked = $this->attempt(fn (Client $devnull): bool => $devnull->unlinkApple());

        if ($linked !== null) {
            $this->appleLinked = $linked;
        }
    }

    #[On(ButtonPressed::class)]
    public function onAlertButton(string $label = '', ?string $id = null): void
    {
        if ($id === self::ForgetAlert && $label === 'Forget') {
            $this->forget();
        }

        if ($id === self::UnlinkAppleAlert && $label === 'Unlink') {
            $this->unlinkApple();
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
        return view('native.this-device', [
            'remote' => config('devnull.url'),
            'offersApple' => AppleSignIn::isAvailable(),
            'appleStatus' => match ($this->appleLinked) {
                true => 'linked',
                false => 'not linked',
                null => 'unknown',
            },
        ]);
    }

    /**
     * Links the Apple ID Apple just vouched for. devnull says so when it's
     * already linked to another account, and that lands in $error.
     */
    protected function useAppleToken(#[SensitiveParameter] string $identityToken, #[SensitiveParameter] string $nonce): void
    {
        $linked = $this->attempt(fn (Client $devnull): bool => $devnull->linkApple($identityToken, $nonce));

        if ($linked !== null) {
            $this->appleLinked = $linked;
        }
    }

    protected function appleFailed(string $message): void
    {
        $this->error = $message;
    }
}
