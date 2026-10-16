<?php

use App\Devnull\Client;
use App\Devnull\Timeline;
use App\NativeComponents\Log;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;
use Native\Mobile\Testing\TestableComponent;

beforeEach(function () {
    config(['devnull.url' => 'http://devnull.test']);
    signIn();
});

/**
 * The log requests sent so far, as "path?query".
 *
 * @return list<string>
 */
function logRequestsSent(): array
{
    return Http::recorded(fn (Request $request): bool => str_contains($request->url(), '/api/v1/log/'))
        ->map(fn (array $pair): string => substr($pair[0]->url(), strlen('http://devnull.test/api/v1/')))
        ->values()
        ->all();
}

/**
 * Commit ids in the order the log shows them.
 *
 * @return list<string>
 */
function shownLogIds(TestableComponent $screen): array
{
    return array_map(fn ($row): string => $row->commit->id, array_values(array_filter(
        $screen->instance()->render()->getData()['rows'],
        fn ($row): bool => $row->isCommit(),
    )));
}

/**
 * A ranked Trending page, as devnull sends it.
 *
 * @param  list<array<string, mixed>>  $commits
 * @param  list<array<string, mixed>>|null  $latest  only sent for ?since=
 * @return array<string, mixed>
 */
function trendingJson(array $commits, string $newest, ?array $latest = null, int $count = 0): array
{
    return [
        ...pageJson($commits, path: 'http://devnull.test/api/v1/log/trending'),
        'log' => 'trending',
        'is_ranked' => true,
        'newest_commit_id' => $newest,
        ...$latest === null ? [] : ['latest_commits' => $latest, 'new_commits_count' => $count],
        'suggestions' => [],
    ];
}

function ada(): array
{
    return userJson(['id' => 'u2', 'username' => 'ada', 'name' => 'Ada', 'lane' => 2]);
}

it('has the web\'s three logs, each asked for by name, and no Everyone', function () {
    serveApi(fn (Request $request) => Http::response(pageJson([commitJson(['id' => 'c-'.basename(parse_url($request->url(), PHP_URL_PATH)), 'body' => 'From '.basename(parse_url($request->url(), PHP_URL_PATH))])])));

    $screen = Native::visit('/log')->assertSee('From following');

    $screen->tap('tab-latest')->assertSet('log', 'latest')->assertSee('From latest')->assertDontSee('From following');
    $screen->tap('tab-trending')->assertSet('log', 'trending')->assertSee('From trending')->assertDontSee('From latest');
    $screen->tap('tab-following')->assertSet('log', 'following')->assertSee('From following');

    expect(array_map(fn (string $sent): string => strtok($sent, '?'), logRequestsSent()))->toContain('log/following', 'log/latest', 'log/trending')
        ->and(implode(' ', logRequestsSent()))->not->toContain('everyone');

    // A log that isn't one of the three is never asked for.
    $before = count(Http::recorded());
    $screen->call('show', 'everyone')->assertSet('log', 'following');

    expect(count(Http::recorded()))->toBe($before);
});

it('asks Following and Latest what came in since the newest commit showing, and counts the pill by devnull', function () {
    holdRefreshes();
    keep('log/latest', pageJson([commitJson(['id' => 'c1', 'body' => 'Seen before'])]));

    // 25 came in: a page of 20, none of them showing yet.
    $newer = array_map(fn (int $n): array => commitJson(['id' => "n{$n}", 'body' => "New {$n}", 'author' => ada()]), range(1, 20));
    serveApi([
        'GET /api/v1/log/following' => [pageJson([])],
        'GET /api/v1/log/latest' => [[...pageJson($newer, 'page2'), 'new_commits_count' => 25]],
        'GET /api/v1/me' => [['data' => userWithCountsJson(), 'unread_notifications_count' => 0]],
    ]);

    $screen = finishRefreshes(Native::visit('/log')->tap('tab-latest'))
        ->assertSee('Seen before')
        ->assertSee('25 new commits')
        ->assertDontSee('20+');

    expect(logRequestsSent())->toContain('log/latest?since=c1');

    $screen->tap('new-commits')->assertSee('New 1')->assertDontSee('25 new commits');

    expect(shownLogIds($screen)[0])->toBe('n1');
});

it('keeps a log without the since it was asked with, or the counts that came with it', function () {
    holdRefreshes();
    keep('log/following', pageJson([commitJson(['id' => 'c1', 'body' => 'Seen before'])]));
    serveApi([
        'GET /api/v1/log/following' => [[...pageJson([commitJson(['id' => 'c2', 'body' => 'Newer', 'author' => ada()]), commitJson(['id' => 'c1', 'body' => 'Seen before'])]), 'new_commits_count' => 1]],
        'GET /api/v1/me' => [['data' => userWithCountsJson(), 'unread_notifications_count' => 0]],
    ]);

    finishRefreshes(Native::visit('/log'))->assertSee('1 new commit');

    expect(kept('log/following'))->not->toHaveKey('new_commits_count')
        ->and(array_column(kept('log/following')['data'], 'id'))->toBe(['c2', 'c1'])
        ->and(kept('log/following', ['since' => 'anything']))->toBe(kept('log/following'));
});

it('suggests people to follow on an empty Following log, and follows one in place', function () {
    $suggestion = commitJson(['id' => 's1', 'body' => 'Shipping a CLI today', 'author' => ada()]);
    $followed = false;

    serveApi(function (Request $request) use ($suggestion, &$followed) {
        $path = parse_url($request->url(), PHP_URL_PATH);

        if ($request->method() === 'POST' && $path === '/api/v1/users/ada/follow') {
            $followed = true;

            return Http::response(null, 204);
        }

        return match (true) {
            $path === '/api/v1/log/following' => Http::response($followed
                ? pageJson([commitJson(['id' => 'c1', 'body' => 'Now in your log', 'author' => ada()])])
                : [...pageJson([]), 'suggestions' => [$suggestion]]),
            default => Http::response(['data' => userWithCountsJson(), 'unread_notifications_count' => 0]),
        };
    });

    $screen = Native::visit('/log')
        ->assertSee('fatal: your log does not have any commits yet')
        ->assertSee('Here is what a few developers committed lately:')
        ->assertSee('Shipping a CLI today')
        ->assertSee('@ada')
        ->assertSee('Trending');

    $screen->tap('follow-ada')
        ->assertSee('Now in your log')
        ->assertDontSee('Here is what a few developers committed lately:');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && $request->url() === 'http://devnull.test/api/v1/users/ada/follow');
});

it('points an empty Following log at Trending when there is nobody to suggest', function () {
    Http::fake(['devnull.test/*' => Http::response(pageJson([]))]);

    Native::visit('/log')
        ->assertSee('Find someone worth following on ')
        ->tap('empty-trending')
        ->assertSet('log', 'trending');
});

it('ranks Trending afresh when it opens, over the ranking kept last time', function () {
    holdRefreshes();
    keep('log/trending', trendingJson([commitJson(['id' => 'old-top', 'body' => 'Top yesterday']), commitJson(['id' => 'c2', 'body' => 'Second'])], newest: 'c2'));
    serveApi([
        'GET /api/v1/log/trending' => [trendingJson([commitJson(['id' => 'c2', 'body' => 'Second']), commitJson(['id' => 'new-top', 'body' => 'Top today'])], newest: 'n9')],
        'GET /api/v1/log/following' => [pageJson([])],
        'GET /api/v1/me' => [['data' => userWithCountsJson(), 'unread_notifications_count' => 0]],
    ]);

    $screen = Native::visit('/log')->tap('tab-trending');

    // The kept ranking paints at once, with no spinner.
    expect(shownLogIds($screen))->toBe(['old-top', 'c2'])
        ->and(showsLoading($screen->tree()))->toBeFalse();

    finishRefreshes($screen);

    // Opening asks for the ranking itself, not what's new since it.
    expect(shownLogIds($screen))->toBe(['c2', 'new-top'])
        ->and(logRequestsSent())->toContain('log/trending')
        ->and($screen->get('rankedSince'))->toBe('n9');
});

it('never re-ranks Trending under you: later answers only change rows in place, and newer commits wait in the pill', function () {
    holdRefreshes();
    serveApi([
        'GET /api/v1/log/trending' => [trendingJson([commitJson(['id' => 'a', 'body' => 'First']), commitJson(['id' => 'b', 'body' => 'Second', 'stars_count' => 1])], newest: 'b')],
        'GET /api/v1/log/following' => [pageJson([])],
        'GET /api/v1/me' => [['data' => userWithCountsJson(), 'unread_notifications_count' => 0]],
    ]);

    $screen = finishRefreshes(Native::visit('/log')->tap('tab-trending'));

    expect(shownLogIds($screen))->toBe(['a', 'b']);

    // Half a minute later the ranking has moved, b has stars, and two commits are newer than b.
    serveApi([
        'GET /api/v1/log/trending' => [trendingJson(
            [commitJson(['id' => 'b', 'body' => 'Second', 'stars_count' => 9]), commitJson(['id' => 'a', 'body' => 'First']), commitJson(['id' => 'z', 'body' => 'Climbing'])],
            newest: 'n2',
            latest: [
                commitJson(['id' => 'n2', 'body' => 'Newest', 'author' => ada(), 'created_at' => now()->subSeconds(10)->toIso8601ZuluString()]),
                commitJson(['id' => 'n1', 'body' => 'Newer', 'author' => ada(), 'created_at' => now()->subMinute()->toIso8601ZuluString()]),
            ],
            count: 2,
        )],
        'GET /api/v1/me' => [['data' => userWithCountsJson(), 'unread_notifications_count' => 0]],
    ]);

    finishRefreshes($screen->call('checkForNewCommits'))
        ->assertSee('2 new commits')
        ->assertDontSee('Climbing')
        ->assertDontSee('Newest');

    expect(shownLogIds($screen))->toBe(['a', 'b'])
        ->and($screen->instance()->commits[1]->starsCount)->toBe(9)
        ->and(logRequestsSent())->toContain('log/trending?since=b');

    $requests = count(Http::recorded());
    $listKey = $screen->get('listKey');

    // The pill puts them above the ranking with no request, and counting starts again from the newest of them.
    $screen->tap('new-commits')->assertDontSee('2 new commits');

    expect(shownLogIds($screen))->toBe(['n2', 'n1', 'a', 'b'])
        ->and(dayMarkers($screen))->toBe([Timeline::JustNow, Timeline::TopCommits])
        ->and(count(Http::recorded()))->toBe($requests)
        ->and($screen->get('rankedSince'))->toBe('n2')
        ->and($screen->get('listKey'))->toBe($listKey + 1);

    finishRefreshes($screen->call('checkForNewCommits'));

    expect(logRequestsSent())->toContain('log/trending?since=n2');
});

it('ranks Trending again on pull to refresh, which clears what was put above it', function () {
    holdRefreshes();
    serveApi([
        'GET /api/v1/log/trending' => [trendingJson([commitJson(['id' => 'a'])], newest: 'a', latest: [commitJson(['id' => 'n1', 'author' => ada()])], count: 1)],
        'GET /api/v1/log/following' => [pageJson([])],
        'GET /api/v1/me' => [['data' => userWithCountsJson(), 'unread_notifications_count' => 0]],
    ]);

    $screen = finishRefreshes(Native::visit('/log')->tap('tab-trending'));
    finishRefreshes($screen->call('checkForNewCommits'))->tap('new-commits');

    expect(shownLogIds($screen))->toBe(['n1', 'a']);

    serveApi([
        'GET /api/v1/log/trending' => [trendingJson([commitJson(['id' => 'n1', 'author' => ada()]), commitJson(['id' => 'a'])], newest: 'n1')],
        'GET /api/v1/me' => [['data' => userWithCountsJson(), 'unread_notifications_count' => 0]],
    ]);

    finishRefreshes($screen->call('load'));

    expect(shownLogIds($screen))->toBe(['n1', 'a'])
        ->and($screen->get('latestCommits'))->toBe([])
        ->and(dayMarkers($screen))->toBe([]);
});

it('draws Trending with no day markers and no last-visit marker', function () {
    Http::fake(['devnull.test/*' => Http::response(trendingJson([
        commitJson(['id' => 'a', 'created_at' => now()->subDays(3)->toIso8601ZuluString()]),
        commitJson(['id' => 'b', 'created_at' => now()->subMinute()->toIso8601ZuluString(), 'author' => ada()]),
    ], newest: 'b'))]);

    $screen = Native::test(Log::class)->call('show', 'trending');

    expect(dayMarkers($screen))->toBe([])
        ->and(shownLogIds($screen))->toBe(['a', 'b']);

    $screen->assertMissingElement('row', fn (array $node): bool => ($node['ref'] ?? null) === 'last-visit');
});

it('puts a commit made on Trending above the ranking, under Just now', function () {
    serveApi([
        'GET /api/v1/log/trending' => [trendingJson([commitJson(['id' => 'a', 'author' => ada()])], newest: 'a')],
        'GET /api/v1/log/following' => [pageJson([])],
        'POST /api/v1/commits' => [['data' => commitJson(['id' => 'mine', 'body' => 'Fresh from the composer', 'status' => 'pending'])], 201],
        'GET /api/v1/me' => [['data' => userWithCountsJson(), 'unread_notifications_count' => 0]],
    ]);

    $screen = Native::visit('/log')->tap('tab-trending')
        ->pressUp('composer-field')
        ->input('body', 'Fresh from the composer')
        ->tap('commit');

    expect(shownLogIds($screen))->toBe(['mine', 'a'])
        ->and(dayMarkers($screen))->toBe([Timeline::JustNow, Timeline::TopCommits])
        ->and($screen->get('rankedSince'))->toBe('a');
});

it('lists a ranked commit once, even when it moves to the next page', function () {
    Http::fake(function (Request $request) {
        return str_contains($request->url(), 'cursor=page2')
            ? Http::response(pageJson([commitJson(['id' => 'b']), commitJson(['id' => 'c'])]))
            : Http::response([...trendingJson([commitJson(['id' => 'a']), commitJson(['id' => 'b'])], newest: 'c'), ...pageJson([commitJson(['id' => 'a']), commitJson(['id' => 'b'])], 'page2')]);
    });

    $screen = Native::test(Log::class)->call('show', 'trending')->call('loadMore');

    expect(shownLogIds($screen))->toBe(['a', 'b', 'c']);
});

it('drops a commit you deleted from Trending when you come back to it', function () {
    serveApi([
        'GET /api/v1/log/trending' => [trendingJson([commitJson(['id' => 'mine', 'body' => 'Mine']), commitJson(['id' => 'a', 'author' => ada()])], newest: 'a')],
        'GET /api/v1/log/following' => [pageJson([])],
        'DELETE /api/v1/commits/mine' => [null, 204],
        'GET /api/v1/me' => [['data' => userWithCountsJson(), 'unread_notifications_count' => 0]],
    ]);

    $screen = Native::visit('/log')->tap('tab-trending');
    app(Client::class)->deleteCommit('mine');

    $screen->call('onResume');

    expect(shownLogIds($screen))->toBe(['a']);
});
