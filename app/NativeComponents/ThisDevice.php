<?php

namespace App\NativeComponents;

use App\Devnull\Account;
use App\Devnull\AppleSignIn;
use App\Devnull\Client;
use App\Devnull\Data\CurrentUser;
use App\Devnull\DeviceKey;
use App\Devnull\Exceptions\KeychainUnavailable;
use App\NativeComponents\Concerns\RefreshesInBackground;
use App\NativeComponents\Concerns\SignsInWithApple;
use App\NativeComponents\Concerns\TalksToDevnull;
use Illuminate\View\View;
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Facades\Browser;
use Native\Mobile\Facades\Dialog;
use SensitiveParameter;

/**
 * This phone's fingerprint, as your devices on dev-null.sh list it, reached
 * from your own profile. Where the app talks to only shows when it isn't
 * dev-null.sh.
 *
 * On iOS it also links an Apple ID to your account, as a second way in on a
 * new phone. The account stays the GitHub one: Apple only signs you into it.
 * Whether one is linked shows as devnull last said, and is asked again behind it.
 */
class ThisDevice extends NativeComponent
{
    use RefreshesInBackground;
    use SignsInWithApple;
    use TalksToDevnull;

    public const string ForgetAlert = 'forget-device';

    public const string UnlinkAppleAlert = 'unlink-apple';

    public string $fingerprint = '';

    /**
     * Whether devnull has an Apple ID linked to you, or null until it says.
     */
    public ?bool $appleLinked = null;

    public function mount(): void
    {
        try {
            $this->fingerprint = app(DeviceKey::class)->current()->fingerprint();
        } catch (KeychainUnavailable $exception) {
            $this->error = $exception->getMessage();

            return;
        }

        if (($kept = $this->cached('me')) !== null) {
            $this->appleLinked = CurrentUser::fromArray($kept)->appleLinked;
        }

        $this->revalidate();
    }

    protected function revalidate(): void
    {
        if (AppleSignIn::isAvailable()) {
            $this->refresh('me', [], function (array $json): void {
                $this->appleLinked = CurrentUser::fromArray($json)->appleLinked;
            });
        }
    }

    protected function showsContent(): bool
    {
        return $this->appleLinked !== null;
    }

    public function navTitle(): string
    {
        return 'This device';
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
            'remote' => self::remote(),
            'offersApple' => AppleSignIn::isAvailable(),
            'appleStatus' => match ($this->appleLinked) {
                true => 'linked',
                false => 'not linked',
                null => 'unknown',
            },
        ]);
    }

    /**
     * Where the app talks to, or null when it's dev-null.sh itself.
     */
    private static function remote(): ?string
    {
        $url = rtrim((string) config('devnull.url'), '/');

        return $url === Client::ProductionUrl ? null : $url;
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
