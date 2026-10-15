<?php

use App\Devnull\Account;
use App\NativeComponents\Log;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

beforeEach(function () {
    config(['devnull.url' => 'http://devnull.test']);
    signIn();
});

it('shows the following log in tab chrome with a compose action', function () {
    Http::fake(['devnull.test/api/v1/log/following' => Http::response(pageJson([
        commitJson(['id' => 'c2', 'body' => 'Second commit', 'replies_count' => 2]),
        commitJson(['id' => 'c1', 'body' => 'First commit', 'user' => userJson(['id' => 'u2', 'username' => 'ada', 'name' => 'Ada'])]),
    ]))]);

    Native::visit('/log')
        ->assertHasTabBar()
        ->assertTabActive('Log')
        ->assertHasTab('Notifications')
        ->assertHasTab('Me')
        ->assertNavTitle('Log')
        ->assertSee('Following')
        ->assertSee('Everyone')
        ->assertSee('Second commit')
        ->assertSee('First commit')
        ->assertSee('@ada')
        ->assertSee('2 replies')
        ->assertSee('5m')
        ->assertSee("That's the root commit. There's nothing before it.");
});

it('colours each node and segment by the author lane', function () {
    Http::fake(['devnull.test/*' => Http::response(pageJson([
        commitJson(['id' => 'c2', 'user' => userJson(['id' => 'local_dev'])]),
        commitJson(['id' => 'c1', 'user' => userJson(['id' => '1'])]),
    ]))]);

    $light = config('native-ui.theme.light');
    $dark = config('native-ui.theme.dark');

    // local_dev is in lane 5 and "1" in lane 1, as on the web.
    Native::visit('/log')
        ->assertElement('stack', fn (array $node): bool => ($node['style']['bg_color'] ?? null) === $light['lane-5']
            && ($node['props']['dark_bg_color'] ?? null) === $dark['lane-5'])
        ->assertElement('stack', fn (array $node): bool => ($node['style']['bg_color'] ?? null) === $light['lane-1']);
});

it('switches to everyone', function () {
    Http::fake([
        'devnull.test/api/v1/log/following' => Http::response(pageJson([])),
        'devnull.test/api/v1/log/everyone' => Http::response(pageJson([commitJson(['body' => 'Hello from everyone'])])),
    ]);

    Native::visit('/log')
        ->assertSee('fatal: your log does not have any commits yet')
        ->tap('tab-everyone')
        ->assertSet('log', 'everyone')
        ->assertSee('Hello from everyone');
});

it('uses the git voice when everyone is empty', function () {
    Http::fake(['devnull.test/*' => Http::response(pageJson([]))]);

    Native::test(Log::class)
        ->call('show', 'everyone')
        ->assertSee('fatal: no commits yet')
        ->assertSee("Nobody has committed anything. Be the first. It's 280 characters; you'll be fine.");
});

it('loads the next page by cursor', function () {
    Http::fake(function (Request $request) {
        return str_contains($request->url(), 'cursor=page2')
            ? Http::response(pageJson([commitJson(['id' => 'c1', 'body' => 'Older commit'])]))
            : Http::response(pageJson([commitJson(['id' => 'c2', 'body' => 'Newer commit'])], 'page2'));
    });

    Native::visit('/log')
        ->assertSee('Newer commit')
        ->assertDontSee('Older commit')
        ->assertSee('git log --skip=1')
        ->call('loadMore')
        ->assertSee('Older commit')
        ->assertSet('nextCursor', null);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'http://devnull.test/api/v1/log/following?cursor=page2');
});

it('reloads on pull to refresh', function () {
    Http::fake(['devnull.test/*' => Http::response(pageJson([]))]);

    Native::visit('/log')->call('load');

    Http::assertSentCount(2);
});

it('marks pending commits', function () {
    Http::fake(['devnull.test/*' => Http::response(pageJson([commitJson(['status' => 'pending', 'body' => 'Waiting on a mod'])]))]);

    Native::visit('/log')->assertSee('Waiting on a mod')->assertSee('pending');
});

it('opens commits, profiles and the composer', function () {
    Http::fake(['devnull.test/*' => Http::response(pageJson([commitJson(['id' => 'c1'])]))]);

    Native::visit('/log')->tap('commit-c1')->assertNavigatedTo('/commits/c1');
    Native::visit('/log')->call('openProfile', 'ada')->assertNavigatedTo('/users/ada');
    Native::visit('/log')->call('compose')->assertNavigatedTo('/compose')->assertTransition('slide_from_bottom');
});

it('sends you back to onboarding with the server message on a 401', function () {
    Http::fake(['devnull.test/*' => Http::response(['message' => 'This key is not on any devnull account.'], 401)]);

    Native::visit('/log')->assertReplacedWith('/onboarding');

    expect(app(Account::class)->user())->toBeNull();
});

it('prints other errors and keeps the screen', function () {
    Http::fake(['devnull.test/*' => Http::response(['message' => 'Too Many Attempts.'], 429)]);

    Native::visit('/log')->assertSee('Too Many Attempts.')->assertNoNavigation();
});

it('is accessible', function () {
    Http::fake(['devnull.test/*' => Http::response(pageJson([commitJson()]))]);

    Native::visit('/log')->assertAccessible();
});
