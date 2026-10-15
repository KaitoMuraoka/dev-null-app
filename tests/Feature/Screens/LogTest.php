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

it('colours each node and segment by the lane the author picked', function () {
    Http::fake(['devnull.test/*' => Http::response(pageJson([
        commitJson(['id' => 'c2', 'user' => userJson(['id' => 'local_dev', 'lane' => 2])]),
        commitJson(['id' => 'c1', 'user' => userJson(['id' => '1', 'lane' => 4])]),
    ]))]);

    $light = config('native-ui.theme.light');
    $dark = config('native-ui.theme.dark');

    // Hashing those ids would give lanes 5 and 1, so these come from user.lane.
    Native::visit('/log')
        ->assertElement('stack', fn (array $node): bool => ($node['style']['bg_color'] ?? null) === $light['lane-2']
            && ($node['props']['dark_bg_color'] ?? null) === $dark['lane-2'])
        ->assertElement('stack', fn (array $node): bool => ($node['style']['bg_color'] ?? null) === $light['lane-4'])
        ->assertMissingElement('stack', fn (array $node): bool => ($node['style']['bg_color'] ?? null) === $light['lane-5']);
});

it('falls back to hashing the id when the server sends no lane', function () {
    $user = userJson(['id' => 'local_dev']);
    unset($user['lane']);

    Http::fake(['devnull.test/*' => Http::response(pageJson([commitJson(['user' => $user])]))]);

    // local_dev hashes to lane 5, as on the web before lanes could be picked.
    Native::visit('/log')
        ->assertElement('stack', fn (array $node): bool => ($node['style']['bg_color'] ?? null) === config('native-ui.theme.light.lane-5'));
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

it('links mentions to profiles, counting code points so emoji before them do not shift them', function () {
    // Each "🚀" is one code point, two UTF-16 units and four bytes.
    $body = '🚀🚀 pairing with @ada and @Grace-H today';

    Http::fake(['devnull.test/*' => Http::response(pageJson([commitJson([
        'id' => 'c1',
        'body' => $body,
        'mentions' => [
            ['username' => 'ada', 'offset' => 16, 'length' => 4],
            ['username' => 'Grace-H', 'offset' => 25, 'length' => 8],
        ],
    ])]))]);

    $screen = Native::visit('/log');

    expect(paragraphRuns($screen, 'pairing with'))->toBe([
        ['🚀🚀 pairing with '],
        ['@ada', 'mention'],
        [' and '],
        ['@Grace-H', 'mention'],
        [' today'],
    ]);

    $screen->press("openProfile('Grace-H')")->assertNavigatedTo('/users/Grace-H');
});

it('shows inline code on a wash and keeps mentions on both sides of it', function () {
    Http::fake(['devnull.test/*' => Http::response(pageJson([commitJson([
        'body' => '@ada ran `composer test` for @bob',
        'body_segments' => [
            ['type' => 'text', 'text' => '@ada ran '],
            ['type' => 'code', 'code' => 'composer test'],
            ['type' => 'text', 'text' => ' for @bob'],
        ],
        'mentions' => [['username' => 'ada', 'offset' => 0, 'length' => 4], ['username' => 'bob', 'offset' => 29, 'length' => 4]],
    ])]))]);

    expect(paragraphRuns(Native::visit('/log'), 'composer test'))->toBe([
        ['@ada', 'mention'],
        [' ran '],
        ['composer test', 'code'],
        [' for '],
        ['@bob', 'mention'],
    ]);
});

it('shows a link preview as a compact row and opens it in the browser', function () {
    Http::fake(['devnull.test/*' => Http::response(pageJson([commitJson([
        'id' => 'c1',
        'body' => 'Read https://laravel.com/docs?a=1&b=2',
        'link_preview' => [
            'url' => 'https://laravel.com/docs?a=1&b=2',
            'title' => 'Laravel - The PHP Framework',
            'description' => 'Laravel is a web application framework.',
            'image_url' => 'https://laravel.com/og.png',
            'site_name' => null,
        ],
    ])]))]);

    Native::visit('/log')
        ->assertSee('laravel.com')
        ->assertSee('Laravel - The PHP Framework')
        ->assertSee('Laravel is a web application framework.')
        ->assertElement('image', fn (array $node): bool => ($node['props']['src'] ?? null) === 'https://laravel.com/og.png')
        ->tap('link-preview-c1')
        ->assertNativeCalled('Browser.OpenInApp', fn (array $params): bool => $params['url'] === 'https://laravel.com/docs?a=1&b=2')
        ->assertNoNavigation();
});

it('waits for a link preview to have a title, as the web does', function () {
    Http::fake(['devnull.test/*' => Http::response(pageJson([commitJson([
        'id' => 'c1',
        'link_preview' => ['url' => 'https://example.com', 'title' => null, 'description' => null, 'image_url' => null, 'site_name' => null],
    ])]))]);

    Native::visit('/log')->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'link-preview-c1');
});

it('holds images at their aspect ratio, so one that never loads leaves the row intact', function () {
    // Your own pending commit's images 404 for now. The space is held on a wash either way.
    Http::fake(['devnull.test/*' => Http::response(pageJson([commitJson([
        'id' => 'c1',
        'status' => 'pending',
        'body' => 'Look at this',
        'images' => [['id' => 'i1', 'url' => 'http://devnull.test/commit-images/i1', 'width' => 640, 'height' => 480]],
    ])]))]);

    Native::visit('/log')
        ->assertSee('Look at this')
        ->assertSee('pending')
        ->assertElement('column', fn (array $node): bool => abs(($node['layout']['aspect_ratio'] ?? 0) - 640 / 480) < 0.001
            && ($node['style']['bg_color'] ?? null) === config('native-ui.theme.light.wash'))
        ->assertElement('image', fn (array $node): bool => ($node['props']['src'] ?? null) === 'http://devnull.test/commit-images/i1')
        ->tap('image-i1')
        ->assertNativeCalled('Browser.OpenInApp', fn (array $params): bool => $params['url'] === 'http://devnull.test/commit-images/i1');
});

it('lays out several images in the web grid', function () {
    Http::fake(['devnull.test/*' => Http::response(pageJson([commitJson([
        'id' => 'c1',
        'images' => [
            ['id' => 'i1', 'url' => 'https://img.test/1', 'width' => 10, 'height' => 10],
            ['id' => 'i2', 'url' => 'https://img.test/2', 'width' => 10, 'height' => 10],
            ['id' => 'i3', 'url' => 'https://img.test/3', 'width' => 10, 'height' => 10],
        ],
    ])]))]);

    Native::visit('/log')
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'image-i3' && ($node['a11y']['label'] ?? $node['props']['a11y_label'] ?? null) !== null)
        ->tap('image-i3')
        ->assertNativeCalled('Browser.OpenInApp', fn (array $params): bool => $params['url'] === 'https://img.test/3');
});

it('prints stars and saves without offering to change them', function () {
    Http::fake(['devnull.test/*' => Http::response(pageJson([
        commitJson(['id' => 'c2', 'stars_count' => 3, 'starred' => true, 'is_saved' => true, 'replies_count' => 1]),
        commitJson(['id' => 'c1', 'stars_count' => 1, 'starred' => false]),
        commitJson(['id' => 'c0']),
    ]))]);

    Native::visit('/log')
        ->assertSee('★ 3')
        ->assertSee('☆ 1')
        ->assertSee('1 reply')
        ->assertElement('text', fn (array $node): bool => ($node['ref'] ?? null) === 'stars-c2'
            && ($node['props']['color'] ?? null) === config('native-ui.theme.light.primary'))
        ->assertElement('icon', fn (array $node): bool => ($node['ref'] ?? null) === 'saved-c2')
        ->assertMissingElement('icon', fn (array $node): bool => ($node['ref'] ?? null) === 'saved-c1')
        ->assertMissingElement('row', fn (array $node): bool => ($node['ref'] ?? null) === 'actions-c0')
        ->assertMissingElement('pressable', fn (array $node): bool => str_starts_with($node['ref'] ?? '', 'stars-'));
});

it('is accessible', function () {
    Http::fake(['devnull.test/*' => Http::response(pageJson([commitJson()]))]);

    Native::visit('/log')->assertAccessible();
});
