<?php

namespace App\NativeComponents\Concerns;

use App\Devnull\Account;
use App\Devnull\Client;
use App\Devnull\Data\User;
use App\Devnull\Exceptions\ApiException;
use App\Devnull\Exceptions\KeychainUnavailable;
use App\Devnull\Exceptions\Unauthenticated;
use Closure;

/**
 * Calls the devnull API from a screen.
 *
 * A 401 means this key is no longer on a devnull user's GitHub account, so
 * the screen hands over to onboarding with the server's message. Any other
 * failure lands in $error for the screen to print.
 */
trait TalksToDevnull
{
    public ?string $error = null;

    /**
     * @template TResult
     *
     * @param  Closure(Client): TResult  $call
     * @return TResult|null
     */
    protected function attempt(Closure $call): mixed
    {
        try {
            $result = $call(app(Client::class));
            $this->error = null;

            return $result;
        } catch (Unauthenticated $exception) {
            app(Account::class)->forget();
            $this->replace('/onboarding', ['message' => $exception->getMessage()]);
        } catch (ApiException $exception) {
            $this->error = $exception->firstError();
        } catch (KeychainUnavailable $exception) {
            $this->error = $exception->getMessage();
        }

        return null;
    }

    protected function me(): ?User
    {
        return app(Account::class)->user();
    }

    /**
     * Asks devnull how many notifications are unread, for the badge on the
     * Notifications tab, and picks up any change to you while it's there.
     * The badge is a nicety, so a failure here is never put on the screen.
     */
    protected function refreshUnreadNotifications(): void
    {
        try {
            $current = app(Client::class)->me();
        } catch (ApiException|KeychainUnavailable) {
            return;
        }

        if ($current->user->username !== '') {
            app(Account::class)->remember($current->user);
        }

        app(Account::class)->rememberUnreadNotifications($current->unreadNotificationsCount);
    }
}
