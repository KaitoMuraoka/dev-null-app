<?php

namespace App\NativeComponents;

use App\Devnull\Account;
use App\Devnull\Client;
use App\Devnull\Data\AuthorizationStatus;
use App\Devnull\Data\PairingCode;
use App\Devnull\Data\User;
use App\Devnull\DeviceKey;
use App\Devnull\Display;
use App\Devnull\Exceptions\ApiException;
use App\Devnull\Exceptions\GitHubException;
use App\Devnull\Exceptions\GitHubUnreachable;
use App\Devnull\Exceptions\KeychainUnavailable;
use App\Devnull\GitHub;
use App\Devnull\GitHubStep;
use App\Devnull\GitHubToken;
use App\NativeComponents\Layouts\TabsLayout;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Native\Mobile\Attributes\Poll;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Facades\Browser;
use Native\Mobile\Facades\Device;
use Native\Mobile\Facades\Share;
use NativePHP\Clipboard\Facades\Clipboard;
use SensitiveParameter;

/**
 * This phone has its own SSH key. Add it to GitHub once and devnull knows who you are.
 *
 * With a GitHub App configured, the app adds the key itself through GitHub's
 * device flow. Adding it by hand stays available either way, in three steps:
 * copy the key, add it on GitHub, then connect with your username. That ends
 * with a code to enter on the web, since devnull also wants this phone approved.
 */
class Onboarding extends NativeComponent
{
    public const string GitHubNewKeyUrl = 'https://github.com/settings/ssh/new';

    /**
     * devnull re-reads someone's GitHub keys at most every 10 seconds, so a
     * retry waits a little longer than that.
     */
    public const int KeySyncRetrySeconds = 11;

    public const int KeySyncRetries = 3;

    /**
     * What key-syncs answers while the key isn't on the person's GitHub yet.
     */
    public const int KeyNotListedYet = 403;

    /**
     * devnull lets one IP ask key-syncs 10 times a minute, so the approval
     * step asks whether the phone was approved every 6 seconds at most.
     */
    public const int ApprovalPollSeconds = 6;

    /**
     * How long to wait after a 429 that doesn't say.
     */
    public const int RateLimitedSeconds = 60;

    /**
     * How long Copy key reads "Copied", as the web's copy buttons show a check for 2 seconds.
     */
    public const int CopiedSeconds = 2;

    public string $publicKey = '';

    public string $fingerprint = '';

    public string $username = '';

    public ?string $error = null;

    public ?string $notice = null;

    public bool $keyCopied = false;

    public ?string $copyError = null;

    public GitHubStep $step = GitHubStep::Start;

    public string $userCode = '';

    public int $codeMinutes = 15;

    public string $login = '';

    public ?string $githubError = null;

    public ?string $githubNotice = null;

    public string $pairingCode = '';

    public string $pairingMessage = '';

    public int $pairingMinutes = 15;

    public ?string $pairingError = null;

    private string $deviceCode = '';

    private string $devicePage = '';

    private int $interval = 5;

    private int $expiresAt = 0;

    private int $nextAttemptAt = 0;

    private int $keySyncAttempts = 0;

    private string $approvalUrl = '';

    private int $pairingExpiresAt = 0;

    private string $deviceName = '';

    private int $copiedUntil = 0;

    /**
     * Set when nothing sent this phone here, yet it already had a key: only
     * the stored sign-in may be gone, so the first tick asks devnull who it is.
     */
    private bool $signInOnTick = false;

    public function mount(DeviceKey $deviceKey): void
    {
        $this->notice = $this->data('message');
        $this->registerCleanup(fn () => app(GitHubToken::class)->forget());

        try {
            $existing = $deviceKey->existing();
            $key = $existing ?? $deviceKey->current();
        } catch (KeychainUnavailable $exception) {
            $this->error = $exception->getMessage();

            return;
        }

        $this->publicKey = $key->openSsh();
        $this->fingerprint = $key->fingerprint();

        if (config('app.debug')) {
            Log::info('devnull device key', ['public_key' => $this->publicKey, 'fingerprint' => $this->fingerprint]);
        }

        $this->approveFromAnotherScreen();

        $this->signInOnTick = $existing !== null && $this->notice === null && $this->step === GitHubStep::Start;
    }

    /**
     * Copies the whole public key, not the masked one on screen, ready to paste on GitHub.
     */
    public function copyKey(): void
    {
        if ($this->publicKey === '') {
            return;
        }

        if (! Clipboard::writeText($this->publicKey)) {
            $this->keyCopied = false;
            $this->copyError = 'fatal: could not copy the key. Share it instead.';

            return;
        }

        $this->keyCopied = true;
        $this->copyError = null;
        $this->copiedUntil = $this->now() + self::CopiedSeconds * 1000;
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
            $answer = app(Client::class)->syncKey($username, deviceName: $this->keyTitle());
        } catch (ApiException $exception) {
            $this->error = $exception->firstError();

            return;
        } catch (KeychainUnavailable $exception) {
            $this->error = $exception->getMessage();

            return;
        }

        $this->username = $username;
        $this->error = null;
        $this->signInOrShowCode($answer);
    }

    /**
     * Starts GitHub's device flow: asks for a code to show here and type on github.com.
     */
    public function connectGitHub(): void
    {
        $github = app(GitHub::class);

        if (! $github->enabled() || $this->publicKey === '') {
            return;
        }

        try {
            $code = $github->requestDeviceCode();
        } catch (GitHubException $exception) {
            $this->startOver(error: $exception->getMessage());

            return;
        }

        $this->startOver();
        $this->step = GitHubStep::Authorizing;
        $this->deviceCode = $code->deviceCode;
        $this->userCode = $code->userCode;
        $this->devicePage = $code->pageToOpen();
        $this->codeMinutes = max(1, intdiv($code->expiresIn, 60));
        $this->interval = $code->interval;
        $this->expiresAt = $this->now() + $code->expiresIn * 1000;
        $this->waitSeconds($this->interval);
    }

    public function openDevicePage(): void
    {
        if ($this->devicePage !== '') {
            Browser::inApp($this->devicePage);
        }
    }

    public function shareCode(): void
    {
        if ($this->userCode !== '') {
            Share::file('', $this->userCode, '');
        }
    }

    public function cancelGitHub(): void
    {
        $this->startOver();
    }

    public function openApprovalPage(): void
    {
        if ($this->approvalUrl !== '') {
            Browser::inApp($this->approvalUrl);
        }
    }

    /**
     * Shares where to enter the code as well as the code, for approving the
     * phone from a computer.
     */
    public function sharePairingCode(): void
    {
        if ($this->pairingCode !== '') {
            Share::file('', $this->pairingMessage !== '' ? $this->pairingMessage : $this->pairingCode, '');
        }
    }

    public function cancelApproval(): void
    {
        $this->startOver();
    }

    /**
     * GitHub sets the pace of the device flow and devnull the pace of key
     * syncs, so both wait between requests. Ticking every second to see if
     * either is due keeps the screen responsive, where sleeping would freeze it.
     * The same tick turns Copy key's "Copied" back once its moment is up.
     */
    #[Poll(1000)]
    public function tick(): void
    {
        if ($this->keyCopied && $this->now() >= $this->copiedUntil) {
            $this->keyCopied = false;
        }

        if ($this->signInOnTick) {
            $this->signInOnTick = false;

            if ($this->step === GitHubStep::Start && $this->signInWithExistingKey()) {
                return;
            }
        }

        if ($this->now() < $this->nextAttemptAt) {
            return;
        }

        match ($this->step) {
            GitHubStep::Authorizing => $this->checkAuthorization(),
            GitHubStep::Listing => $this->syncKeyWithDevnull(),
            GitHubStep::Approving => $this->checkApproval(),
            default => null,
        };
    }

    public function render(): View
    {
        return view('native.onboarding', [
            'canUseGitHub' => app(GitHub::class)->enabled(),
            'logo' => TabsLayout::logo(),
        ]);
    }

    /**
     * Another screen got a 401 because this phone isn't approved yet, and
     * sent the username along. The first tick asks devnull for a code.
     */
    private function approveFromAnotherScreen(): void
    {
        $username = $this->data('approve');

        if (is_string($username) && $username !== '') {
            $this->username = $username;
            $this->step = GitHubStep::Approving;
        }
    }

    /**
     * A key devnull already knows and approved signs in with nothing to do,
     * as after the app's stored sign-in was lost. Any other answer leaves
     * onboarding as it is, without an error: there was nothing to ask for.
     */
    private function signInWithExistingKey(): bool
    {
        try {
            $current = app(Client::class)->me();
        } catch (ApiException|KeychainUnavailable) {
            return false;
        }

        if ($current->user->username === '') {
            return false;
        }

        app(Account::class)->remember($current->user);
        app(Account::class)->rememberUnreadNotifications($current->unreadNotificationsCount);
        $this->replace('/log');

        return true;
    }

    private function checkAuthorization(): void
    {
        if ($this->now() >= $this->expiresAt) {
            $this->expire();

            return;
        }

        try {
            $authorization = app(GitHub::class)->requestToken($this->deviceCode);
        } catch (GitHubUnreachable $exception) {
            $this->githubError = $exception->getMessage();
            $this->waitSeconds($this->interval);

            return;
        } catch (GitHubException $exception) {
            $this->startOver(error: $exception->getMessage());

            return;
        }

        $this->githubError = null;

        switch ($authorization->status) {
            case AuthorizationStatus::Granted:
                $this->addKeyToGitHub((string) $authorization->token);
                break;
            case AuthorizationStatus::Pending:
                $this->waitSeconds($this->interval);
                break;
            case AuthorizationStatus::SlowDown:
                $this->interval = max($this->interval + 5, $authorization->interval ?? 0);
                $this->waitSeconds($this->interval);
                break;
            case AuthorizationStatus::Expired:
                $this->expire();
                break;
            case AuthorizationStatus::Denied:
                $this->startOver(notice: 'You cancelled on GitHub, so nothing was added. Connect again whenever you like.');
                break;
        }
    }

    /**
     * The token adds the key, then goes to devnull with the key sync, which
     * checks it's for the same account and approves the phone in the same tap.
     * It's held in memory until devnull answers for good, never on the
     * screen, in the Keychain or on disk.
     */
    private function addKeyToGitHub(#[SensitiveParameter] string $token): void
    {
        try {
            $login = app(GitHub::class)->addSigningKey($token, $this->keyTitle(), $this->publicKey);
        } catch (GitHubException $exception) {
            $this->startOver(error: $exception->getMessage());

            return;
        }

        $this->startOver();
        $this->step = GitHubStep::Listing;
        $this->login = $login;
        app(GitHubToken::class)->hold($token);
        $this->syncKeyWithDevnull();
    }

    /**
     * Asks devnull to find the key on GitHub and approve the phone with the
     * token. A new key can take a moment to show up in GitHub's public list,
     * so a 403 for that is tried again a few times. A 403 about the token, or
     * a 404 for someone who never signed in to devnull, can't be waited out,
     * so those stop at once. Whatever the end, the token is dropped.
     */
    private function syncKeyWithDevnull(): void
    {
        $this->keySyncAttempts++;

        try {
            $answer = app(Client::class)->syncKey($this->login, app(GitHubToken::class)->get(), $this->keyTitle());
        } catch (ApiException $exception) {
            if ($this->isKeyNotListedYet($exception) && $this->keySyncAttempts <= self::KeySyncRetries) {
                $this->waitSeconds(self::KeySyncRetrySeconds);

                return;
            }

            $this->username = $this->login;
            $this->startOver(error: $exception->firstError());

            return;
        } catch (KeychainUnavailable $exception) {
            $this->startOver(error: $exception->getMessage());

            return;
        }

        $this->username = $this->login;
        $this->startOver();
        $this->signInOrShowCode($answer);
    }

    /**
     * key-syncs answers 403 both while GitHub hasn't listed the key yet and
     * when the GitHub token is for another account or GitHub refused it. Only
     * the token ones mention the token.
     */
    private function isKeyNotListedYet(ApiException $exception): bool
    {
        return $exception->status === self::KeyNotListedYet
            && ! str_contains(strtolower($exception->getMessage()), 'token');
    }

    /**
     * Asks devnull whether the phone has been approved on the web yet. Until
     * it has, each answer carries the code to show: the same one until it runs
     * out, then a new one.
     */
    private function checkApproval(): void
    {
        try {
            $answer = app(Client::class)->syncKey($this->username, deviceName: $this->keyTitle());
        } catch (ApiException $exception) {
            $this->keepWaitingOrStop($exception);

            return;
        } catch (KeychainUnavailable $exception) {
            $this->stopApproving($exception->getMessage());

            return;
        }

        $this->signInOrShowCode($answer);
    }

    /**
     * A 429 says how long to wait, and a dropped connection or a failing
     * server are worth asking again. Anything else, such as the key gone from
     * GitHub, can't be waited out, so it goes back to the start.
     */
    private function keepWaitingOrStop(ApiException $exception): void
    {
        if ($exception->status === 429) {
            $seconds = max(self::ApprovalPollSeconds, $exception->retryAfter ?? self::RateLimitedSeconds);

            if ($this->now() >= $this->pairingExpiresAt) {
                $this->pairingCode = '';
            }

            $this->pairingError = $exception->getMessage().' Asking again in '.self::duration($seconds).'.';
            $this->waitSeconds($seconds);

            return;
        }

        if ($exception->status === 0 || $exception->status >= 500) {
            $this->pairingError = $exception->getMessage();
            $this->waitSeconds(self::ApprovalPollSeconds);

            return;
        }

        $this->stopApproving($exception->firstError());
    }

    private function stopApproving(string $error): void
    {
        $this->startOver();
        $this->error = $error;
    }

    private function signInOrShowCode(User|PairingCode $answer): void
    {
        if ($answer instanceof PairingCode) {
            $this->showPairingCode($answer);

            return;
        }

        app(Account::class)->remember($answer);
        $this->replace('/log');
    }

    private function showPairingCode(PairingCode $code): void
    {
        $this->step = GitHubStep::Approving;
        $this->pairingCode = $code->code;
        $this->pairingMessage = $code->message;
        $this->approvalUrl = $code->approvalUrl;
        $this->pairingExpiresAt = $code->expiresAt->getTimestampMs();
        $this->pairingMinutes = max(1, (int) ceil(($this->pairingExpiresAt - $this->now()) / 60_000));
        $this->pairingError = null;
        $this->waitSeconds(self::ApprovalPollSeconds);
    }

    private function expire(): void
    {
        $this->startOver();
        $this->step = GitHubStep::Expired;
    }

    private function startOver(?string $error = null, ?string $notice = null): void
    {
        app(GitHubToken::class)->forget();
        $this->step = GitHubStep::Start;
        $this->deviceCode = '';
        $this->devicePage = '';
        $this->userCode = '';
        $this->nextAttemptAt = 0;
        $this->keySyncAttempts = 0;
        $this->githubError = $error;
        $this->githubNotice = $notice;
        $this->pairingCode = '';
        $this->pairingMessage = '';
        $this->pairingError = null;
        $this->approvalUrl = '';
        $this->pairingExpiresAt = 0;
    }

    /**
     * Names the key on GitHub, and the device on devnull, after the phone, so
     * it's clear what to remove later.
     */
    private function keyTitle(): string
    {
        if ($this->deviceName === '') {
            $info = json_decode((string) Device::getInfo(), true);
            $model = is_array($info) && is_string($info['model'] ?? null) ? trim($info['model']) : '';

            $this->deviceName = 'devnull on '.($model === '' ? 'a phone' : $model);
        }

        return $this->deviceName;
    }

    private static function duration(int $seconds): string
    {
        return match (true) {
            $seconds < 60 => Display::count($seconds, 'second', 'seconds'),
            $seconds < 3600 => Display::count((int) ceil($seconds / 60), 'minute', 'minutes'),
            default => Display::count((int) ceil($seconds / 3600), 'hour', 'hours'),
        };
    }

    private function waitSeconds(int $seconds): void
    {
        $this->nextAttemptAt = $this->now() + $seconds * 1000;
    }

    private function now(): int
    {
        return now()->getTimestampMs();
    }
}
