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
 * The log devnull sends now, and an unread count for the badge.
 *
 * @param  list<array<string, mixed>>  $commits
 */
function fakeFreshLog(array $commits, ?string $cursor = null, array $extra = []): void
{
    serveApi([
        'GET /api/v1/log/following' => [pageJson($commits, $cursor)],
        'GET /api/v1/me' => [['data' => userWithCountsJson(), 'unread_notifications_count' => 0]],
        ...$extra,
    ]);
}

function logRequests(): int
{
    return count(Http::recorded(fn (Request $request): bool => str_contains($request->url(), '/api/v1/log/following')));
}

/**
 * @return list<string>
 */
function shownCommitIds(TestableComponent $screen): array
{
    return array_map(fn ($commit): string => $commit->id, $screen->instance()->commits);
}

it('opens on the log kept last time, with no request and no spinner, then refreshes behind it', function () {
    holdRefreshes();
    keep('log/following', pageJson([commitJson(['id' => 'c1', 'body' => 'Kept from last time', 'stars_count' => 1])]));
    fakeFreshLog([commitJson(['id' => 'c1', 'body' => 'Kept from last time', 'stars_count' => 4])]);

    $frames = count(Native::fakeBridge()->publishes);
    $screen = Native::visit('/log')->assertSee('Kept from last time');

    // One frame, the kept log: no placeholder before it, no spinner in it, nothing asked of devnull yet.
    expect(count($screen->bridge()->publishes) - $frames)->toBe(1)
        ->and(showsLoading($screen->tree()))->toBeFalse()
        ->and(logRequests())->toBe(0)
        ->and(refreshesSent($screen))->toBeGreaterThan(0);

    finishRefreshes($screen);

    expect(logRequests())->toBe(1)
        ->and($screen->instance()->commits[0]->starsCount)->toBe(4);
});

it('shows skeleton rows the first time only, until the log lands', function () {
    holdRefreshes();
    fakeFreshLog([commitJson(['id' => 'c1', 'body' => 'Fresh commit'])]);

    $screen = Native::visit('/log')
        ->assertElement('row', fn (array $node): bool => ($node['ref'] ?? null) === 'loading-1')
        ->assertDontSee('fatal: your log does not have any commits yet');

    finishRefreshes($screen)
        ->assertSee('Fresh commit')
        ->assertMissingElement('row', fn (array $node): bool => ($node['ref'] ?? null) === 'loading-1');

    // Next time it opens on what it fetched.
    $again = Native::visit('/log');

    expect(showsLoading($again->tree()))->toBeFalse();
    $again->assertSee('Fresh commit');
});

it('holds new commits in a pill with the right count, and puts them on top without a request', function () {
    holdRefreshes();
    keep('log/following', pageJson([commitJson(['id' => 'c1', 'body' => 'Seen before'])]));
    fakeFreshLog([
        commitJson(['id' => 'c3', 'body' => 'Newest', 'author' => userJson(['id' => 'u2', 'username' => 'ada'])]),
        commitJson(['id' => 'c2', 'body' => 'Newer', 'author' => userJson(['id' => 'u2', 'username' => 'ada'])]),
        commitJson(['id' => 'c1', 'body' => 'Seen before']),
    ]);

    $screen = finishRefreshes(Native::visit('/log'))
        ->assertSee('2 new commits')
        ->assertDontSee('Newest');

    expect(shownCommitIds($screen))->toBe(['c1']);

    $requests = count(Http::recorded());
    $listKey = $screen->get('listKey');

    $screen->tap('new-commits')
        ->assertSee('Newest')
        ->assertSee('Newer')
        ->assertDontSee('2 new commits');

    expect(shownCommitIds($screen))->toBe(['c3', 'c2', 'c1'])
        ->and(count(Http::recorded()))->toBe($requests)
        ->and(refreshesSent($screen))->toBe(2)
        // A new list key is how the log gets back to the top.
        ->and($screen->get('listKey'))->toBe($listKey + 1);
});

it('says one new commit, and puts the last-visit marker under it once shown', function () {
    $seen = commitJson(['id' => 'c1', 'body' => 'Seen before', 'created_at' => now()->subHour()->toIso8601ZuluString()]);

    // The visit before this one.
    fakeFreshLog([$seen]);
    Native::visit('/log');

    holdRefreshes();
    fakeFreshLog([commitJson(['id' => 'c2', 'body' => 'Just in', 'author' => userJson(['id' => 'u2', 'username' => 'ada'])]), $seen]);

    finishRefreshes(Native::visit('/log'))
        ->assertSee('1 new commit')
        ->assertDontSee('HEAD@{last visit}')
        ->tap('new-commits')
        ->assertSee('Just in')
        ->assertSee('HEAD@{last visit}');
});

it('says when more came in than a page holds', function () {
    holdRefreshes();
    keep('log/following', pageJson([commitJson(['id' => 'c1', 'body' => 'Long ago'])]));
    fakeFreshLog([commitJson(['id' => 'c9', 'body' => 'All new']), commitJson(['id' => 'c8'])], cursor: 'page2');

    $screen = finishRefreshes(Native::visit('/log'))->assertSee('2+ new commits');

    $screen->tap('new-commits')->assertSee('All new')->assertDontSee('Long ago');

    expect(shownCommitIds($screen))->toBe(['c9', 'c8'])
        ->and($screen->get('nextCursor'))->toBe('page2');
});

it('changes counts and stars on rows already showing in place, with no pill', function () {
    holdRefreshes();
    keep('log/following', pageJson([commitJson(['id' => 'c1', 'body' => 'Popular', 'stars_count' => 1, 'replies_count' => 0])]));
    fakeFreshLog([commitJson(['id' => 'c1', 'body' => 'Popular', 'stars_count' => 7, 'replies_count' => 2])]);

    $screen = finishRefreshes(Native::visit('/log'))
        ->assertSee('2 replies')
        ->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'new-commits');

    expect($screen->instance()->commits[0]->starsCount)->toBe(7);
});

it('puts new commits straight in on pull to refresh', function () {
    holdRefreshes();
    keep('log/following', pageJson([commitJson(['id' => 'c1'])]));
    fakeFreshLog([commitJson(['id' => 'c2', 'body' => 'Pulled in']), commitJson(['id' => 'c1'])]);

    $screen = finishRefreshes(Native::visit('/log'))->assertSee('1 new commit');

    finishRefreshes($screen->call('load'))
        ->assertSee('Pulled in')
        ->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'new-commits');

    expect(shownCommitIds($screen))->toBe(['c2', 'c1']);
});

it('checks for new commits every 30 seconds, as the web polls', function () {
    fakeFreshLog([commitJson(['id' => 'c1'])]);

    $screen = Native::visit('/log');

    fakeFreshLog([commitJson(['id' => 'c2', 'body' => 'Polled in']), commitJson(['id' => 'c1'])]);

    $screen->firePoll('checkForNewCommits')->assertSee('1 new commit')->assertDontSee('Polled in');
});

it('keeps the kept log and says quietly that it could not refresh when offline, with a retry', function () {
    holdRefreshes();
    keep('log/following', pageJson([commitJson(['id' => 'c1', 'body' => 'Still here'])]));
    fakeOffline();

    $screen = finishRefreshes(Native::visit('/log'))
        ->assertSee('Still here')
        ->assertSee("warning: couldn't refresh")
        ->assertSet('error', null);

    fakeFreshLog([commitJson(['id' => 'c1', 'body' => 'Still here'])]);

    finishRefreshes($screen->tap('refresh-failed'))
        ->assertDontSee("warning: couldn't refresh")
        ->assertSee('Still here');
});

it('prints the error when there is nothing kept to show', function () {
    holdRefreshes();
    fakeOffline();

    $screen = finishRefreshes(Native::visit('/log'))
        ->assertSee("fatal: unable to access 'http://devnull.test': could not connect to the server")
        ->assertDontSee("warning: couldn't refresh");

    expect(showsLoading($screen->tree()))->toBeFalse();
});

it('signs out and empties the cache when a background refresh gets a 401', function () {
    holdRefreshes();
    keep('log/following', pageJson([commitJson()]));
    Http::fake(['devnull.test/*' => Http::response(['message' => 'This key is not approved.', 'code' => 'device_not_approved'], 401)]);

    finishRefreshes(Native::visit('/log'))->assertReplacedWith('/onboarding');

    signIn();

    expect(kept('log/following'))->toBeNull()
        ->and(app(Account::class)->unreadNotifications())->toBe(0);
});

it('opens the other log at once when it was kept', function () {
    holdRefreshes();
    keep('log/following', pageJson([commitJson(['id' => 'c1', 'body' => 'From following'])]));
    keep('log/everyone', pageJson([commitJson(['id' => 'c2', 'body' => 'From everyone'])]));

    $screen = Native::visit('/log')->tap('tab-everyone')->assertSee('From everyone')->assertDontSee('From following');

    expect(showsLoading($screen->tree()))->toBeFalse()
        ->and(count(Http::recorded()))->toBe(0);
});

it('ignores an answer for the log you have since left', function () {
    holdRefreshes();
    keep('log/everyone', pageJson([commitJson(['id' => 'c2', 'body' => 'From everyone'])]));
    fakeFreshLog([commitJson(['id' => 'c1', 'body' => 'From following'])], extra: [
        'GET /api/v1/log/everyone' => [pageJson([commitJson(['id' => 'c2', 'body' => 'From everyone'])])],
    ]);

    $screen = finishRefreshes(Native::visit('/log')->tap('tab-everyone'))
        ->assertSee('From everyone')
        ->assertDontSee('From following')
        ->assertSet('error', null);

    // It's still kept for when you go back.
    expect(kept('log/following')['data'][0]['body'])->toBe('From following');
});

it('keeps your new commit, so the log opens with it next time', function () {
    fakeApi([
        'GET /api/v1/log/following' => [pageJson([commitJson(['id' => 'c1', 'body' => 'Older'])])],
        'GET /api/v1/me' => [['data' => userWithCountsJson(), 'unread_notifications_count' => 0]],
        'POST /api/v1/commits' => [['data' => commitJson(['id' => 'c9', 'body' => 'Just shipped'])], 201],
    ]);

    Native::visit('/log')->pressUp('composer-field')->input('body', 'Just shipped')->tap('commit');

    holdRefreshes();

    Native::visit('/log')->assertSee('Just shipped')->assertSee('Older');

    expect(array_column(kept('log/following')['data'], 'id'))->toBe(['c9', 'c1']);
});

it('shows a star made on a commit as soon as you come back to the log', function () {
    holdRefreshes();
    keep('log/following', pageJson([commitJson(['id' => 'c1', 'body' => 'Star me', 'stars_count' => 0])]));
    keep('commits/c1', commitPageJson(commitJson(['id' => 'c1', 'body' => 'Star me', 'stars_count' => 0])));
    keep('commits/c1/replies', pageJson([]));
    fakeApi(['POST /api/v1/commits/c1/star' => [['data' => commitJson(['id' => 'c1', 'starred' => true, 'stars_count' => 1])]]]);

    $log = Native::visit('/log');
    $commit = $log->tap('commit-c1')->follow()->tap('star-c1');

    $log = $commit->goBack();

    expect($log->instance()->commits[0]->starred)->toBeTrue()
        ->and($log->instance()->commits[0]->starsCount)->toBe(1);
});

it('drops a commit deleted on its page as soon as you come back to the log', function () {
    holdRefreshes();
    keep('log/following', pageJson([commitJson(['id' => 'c2', 'body' => 'Delete me']), commitJson(['id' => 'c1', 'body' => 'Keep me'])]));
    keep('commits/c2', commitPageJson(commitJson(['id' => 'c2', 'body' => 'Delete me'])));
    fakeApi(['DELETE /api/v1/commits/c2' => [null, 204]]);

    $log = Native::visit('/log');
    $commit = $log->tap('commit-c2')->follow()->call('delete');

    $commit->goBack()->assertDontSee('Delete me')->assertSee('Keep me');
});
