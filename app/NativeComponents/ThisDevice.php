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
 * Your account, reached from your own profile: this phone's fingerprint, as
 * your devices on dev-null.sh list it, signing out, and deleting the account.
 * Where the app talks to only shows when it isn't dev-null.sh.
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

    public const string SignOutAlert = 'sign-out';

    public const string UnlinkAppleAlert = 'unlink-apple';

    public const string DeleteAccountAlert = 'delete-account';

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
        return 'Account';
    }

    /**
     * The devices page on dev-null.sh, where this phone is listed once approved.
     */
    public function openDevices(): void
    {
        Browser::inApp(rtrim((string) config('devnull.url'), '/').'/settings/devices');
    }

    public function confirmSignOut(): void
    {
        Dialog::alert(
            'Sign out?',
            'This deletes the key from this phone. The phone stays listed on dev-null.sh until you remove it there, and signing in again makes a new key.',
            [
                ['label' => 'Cancel', 'style' => 'cancel'],
                ['label' => 'Sign out', 'style' => 'destructive'],
            ],
        )->id(self::SignOutAlert);
    }

    public function confirmDeleteAccount(): void
    {
        Dialog::alert(
            'Delete your account?',
            'Your profile, commits, replies, stars and follows are removed from /dev/null. There is no undo.',
            [
                ['label' => 'Cancel', 'style' => 'cancel'],
                ['label' => 'Delete', 'style' => 'destructive'],
            ],
        )->id(self::DeleteAccountAlert);
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
        if ($id === self::SignOutAlert && $label === 'Sign out') {
            $this->forget();
        }

        if ($id === self::DeleteAccountAlert && $label === 'Delete') {
            $this->deleteAccount();
        }

        if ($id === self::UnlinkAppleAlert && $label === 'Unlink') {
            $this->unlinkApple();
        }
    }

    /**
     * Signs out: the key goes from this phone, and with it who you are and
     * every answer kept for you (see Account::forget).
     */
    public function forget(): void
    {
        app(DeviceKey::class)->forget();
        app(Account::class)->forget();

        $this->replace('/onboarding');
    }

    /**
     * Deletes the account on devnull, which takes its devices with it, then
     * signs out. When devnull can't, the error shows and you stay signed in.
     */
    public function deleteAccount(): void
    {
        $deleted = $this->attempt(function (Client $devnull): bool {
            $devnull->deleteAccount();

            return true;
        });

        if ($deleted === true) {
            $this->forget();
        }
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
