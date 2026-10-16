<?php

use App\Devnull\Account;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

beforeEach(function () {
    config(['devnull.url' => 'http://devnull.test']);
    signIn();
});

/**
 * @param  list<array<string, mixed>>  $groups
 */
function serveNotifications(array $groups, int $unread = 0): void
{
    serveApi([
        'GET /api/v1/notifications' => [pageJson($groups)],
        'GET /api/v1/me' => [['data' => userWithCountsJson(), 'unread_notifications_count' => $unread]],
    ]);
}

it('keeps notifications as read, since devnull marked them read as it sent them', function () {
    serveNotifications([notificationJson('followed')]);

    Native::visit('/notifications')->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'notification-n-followed'
        && ($node['style']['bg_color'] ?? null) === config('native-ui.theme.light.primary-soft'));

    expect(kept('notifications')['data'][0]['unread_count'])->toBe(0);
});

it('opens on the notifications kept last time, with no request and no spinner, then puts new ones on top quietly', function () {
    holdRefreshes();
    keep('notifications', pageJson([notificationJson('followed')]));
    serveNotifications([notificationJson('mentioned'), notificationJson('followed', ['unread_count' => 0])], unread: 0);
    app(Account::class)->rememberUnreadNotifications(1);

    $screen = Native::visit('/notifications')->assertSee('followed you');

    expect(showsLoading($screen->tree()))->toBeFalse()
        ->and(count(Http::recorded()))->toBe(0)
        // Kept as read: it's been seen.
        ->and($screen->instance()->notifications[0]->isUnread)->toBeFalse();

    finishRefreshes($screen)->assertSee('mentioned you');

    expect(array_map(fn ($group): string => $group->id, $screen->instance()->notifications))->toBe(['n-mentioned', 'n-followed'])
        ->and($screen->instance()->notifications[0]->isUnread)->toBeTrue()
        ->and(app(Account::class)->unreadNotifications())->toBe(0);
});

it('shows a spinner only the first time', function () {
    holdRefreshes();
    serveNotifications([notificationJson('followed')]);

    $screen = Native::visit('/notifications')
        ->assertElement('column', fn (array $node): bool => ($node['ref'] ?? null) === 'loading-notifications')
        ->assertDontSee('Nothing here yet.');

    finishRefreshes($screen)->assertSee('followed you');
});

it('keeps them and says it could not refresh when offline', function () {
    holdRefreshes();
    keep('notifications', pageJson([notificationJson('followed')]));
    fakeOffline();

    finishRefreshes(Native::visit('/notifications'))
        ->assertSee('followed you')
        ->assertSee("warning: couldn't refresh");
});
