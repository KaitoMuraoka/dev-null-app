<?php

use App\Devnull\Account;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;
use Native\Mobile\Testing\TestableComponent;

beforeEach(function () {
    config(['devnull.url' => 'http://devnull.test']);
    signIn();
});

/**
 * @return array<string, mixed>
 */
function meJson(int $unread, array $user = []): array
{
    return ['data' => userWithCountsJson($user, commits: 1, followers: 0, following: 0), 'unread_notifications_count' => $unread];
}

function notificationsBadge(TestableComponent $screen): ?string
{
    $find = function (array $node) use (&$find): ?array {
        if (($node['type'] ?? null) === 'bottom_nav_item' && ($node['props']['label'] ?? null) === 'Notifications') {
            return $node;
        }

        foreach ($node['children'] ?? [] as $child) {
            if (($found = $find($child)) !== null) {
                return $found;
            }
        }

        return null;
    };

    $tab = $find($screen->tree());

    expect($tab)->not->toBeNull('The Notifications tab is missing.');

    return $tab['props']['badge'] ?? null;
}

it('badges the Notifications tab with the unread count once the log loads', function () {
    fakeApi([
        'GET /api/v1/log/following' => [pageJson([commitJson()])],
        'GET /api/v1/me' => [meJson(3)],
    ]);

    $screen = Native::visit('/log');

    expect(notificationsBadge($screen))->toBe('3')
        ->and(app(Account::class)->unreadNotifications())->toBe(3);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'http://devnull.test/api/v1/me');
});

it('shows no badge when everything is read', function () {
    fakeApi([
        'GET /api/v1/log/following' => [pageJson([])],
        'GET /api/v1/me' => [meJson(0)],
    ]);

    expect(notificationsBadge(Native::visit('/log')))->toBeNull();
});

it('clears the badge after the notifications are opened', function () {
    app(Account::class)->rememberUnreadNotifications(2);
    $opened = false;

    Http::fake(function (Request $request) use (&$opened) {
        return match (parse_url($request->url(), PHP_URL_PATH)) {
            '/api/v1/notifications' => tap(Http::response(pageJson([notificationJson('followed')])), function () use (&$opened): void {
                $opened = true;
            }),
            // devnull marks the page read as it sends it.
            '/api/v1/me' => Http::response(meJson($opened ? 0 : 2)),
            default => Http::response(['message' => 'No fake.'], 404),
        };
    });

    $screen = Native::visit('/notifications');

    expect(notificationsBadge($screen))->toBeNull()
        ->and(app(Account::class)->unreadNotifications())->toBe(0);

    $requests = Http::recorded()->map(fn (array $pair): string => parse_url($pair[0]->url(), PHP_URL_PATH))->values()->all();
    expect($requests)->toBe(['/api/v1/notifications', '/api/v1/me']);
});

it('counts unread groups on the badge, as devnull does, not the notifications in them', function () {
    fakeApi([
        'GET /api/v1/notifications' => [pageJson([
            notificationJson('starred', ['actors_count' => 4, 'unread_count' => 4]),
            notificationJson('replied', ['actors_count' => 2, 'unread_count' => 2]),
        ], 'more', 'http://devnull.test/api/v1/notifications')],
        // This page is read now. One more group is unread further down.
        'GET /api/v1/me' => [meJson(1)],
    ]);

    $screen = Native::visit('/notifications');

    expect(notificationsBadge($screen))->toBe('1')
        ->and(app(Account::class)->unreadNotifications())->toBe(1);
});

it('keeps counting what is still unread further down', function () {
    fakeApi([
        'GET /api/v1/notifications' => [pageJson([], 'more')],
        'GET /api/v1/me' => [meJson(140)],
    ]);

    expect(notificationsBadge(Native::visit('/notifications')))->toBe('99+');
});

it('keeps the log on screen when the count cannot be fetched', function () {
    app(Account::class)->rememberUnreadNotifications(4);

    fakeApi([
        'GET /api/v1/log/following' => [pageJson([commitJson(['body' => 'Still here'])])],
        'GET /api/v1/me' => [['message' => 'Too Many Attempts.'], 429],
    ]);

    $screen = Native::visit('/log')
        ->assertSee('Still here')
        ->assertDontSee('Too Many Attempts.')
        ->assertSet('error', null);

    expect(notificationsBadge($screen))->toBe('4');
});

it('picks up the count on your Profile tab and forgets it with the device', function () {
    fakeApi([
        'GET /api/v1/users/local_dev' => [['data' => userWithCountsJson(commits: 0, followers: 0, following: 0), 'is_following' => false, 'activity' => ['scale' => 'week', 'buckets' => []], 'streaks' => ['longest' => 0, 'current' => 0], 'pinned_commit' => null]],
        'GET /api/v1/users/local_dev/commits' => [pageJson([])],
        'GET /api/v1/me' => [meJson(5, ['name' => 'Renamed On GitHub'])],
    ]);

    $screen = Native::visit('/profile');

    expect(notificationsBadge($screen))->toBe('5')
        ->and(app(Account::class)->user()?->name)->toBe('Renamed On GitHub');

    Native::visit('/profile/device')->call('forget');

    expect(app(Account::class)->unreadNotifications())->toBe(0);
});
