<?php

use App\Devnull\Account;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Testing\Native;

beforeEach(function () {
    config(['devnull.url' => 'http://devnull.test']);
    signIn();
});

/**
 * @param  array<string, mixed>  $commit
 * @param  list<array<string, mixed>>  $replies
 * @param  array<string, array{0: mixed, 1?: int}>  $extra
 */
function fakeThread(array $commit = [], array $replies = [], array $extra = []): void
{
    fakeApi([
        'GET /api/v1/commits/c2' => [[
            'data' => commitJson(['id' => 'c2', 'parent_id' => 'c1', 'body' => 'The focused commit', ...$commit]),
            'ancestors' => [commitJson(['id' => 'c1', 'body' => 'The parent commit', 'user' => userJson(['id' => 'u2', 'username' => 'ada', 'name' => 'Ada'])])],
        ]],
        'GET /api/v1/commits/c2/replies' => [pageJson($replies)],
        ...$extra,
    ]);
}

it('shows the ancestors above the commit and the replies below', function () {
    fakeThread(replies: [commitJson(['id' => 'r1', 'parent_id' => 'c2', 'body' => 'First reply', 'user' => userJson(['username' => 'grace', 'name' => 'Grace'])])]);

    Native::visit('/commits/c2')
        ->assertNavTitle('Commit')
        ->assertSee('The parent commit')
        ->assertSee('The focused commit')
        ->assertSee('First reply')
        ->assertSee('1 reply')
        ->assertSee('Reply to @local_dev');
});

it('prints the date the way git log does', function () {
    fakeThread(['created_at' => '2026-10-14T20:13:05Z']);

    Native::visit('/commits/c2')->assertSee('Wed Oct 14 20:13:05 2026 +0000 · c2');
});

it('counts down the reply budget and posts the reply', function () {
    fakeThread(extra: ['POST /api/v1/commits' => [['data' => commitJson(['id' => 'r9', 'parent_id' => 'c2', 'body' => 'A fresh reply'])], 201]]);

    Native::visit('/commits/c2')
        ->assertSee('No replies yet.')
        ->input('reply-input', 'A fresh reply')
        ->assertSee('267')
        ->tap('send-reply')
        ->assertSet('reply', '')
        ->assertSee('A fresh reply')
        ->assertNativeCalled('Dialog.Toast', fn (array $params): bool => $params['message'] === '[reply r9] A fresh reply');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->body() === '{"body":"A fresh reply","parent_id":"c2"}');
});

it('says when a reply is waiting for approval', function () {
    fakeThread(extra: ['POST /api/v1/commits' => [['data' => commitJson(['id' => 'r9', 'status' => 'pending', 'body' => 'Hold on'])], 201]]);

    Native::visit('/commits/c2')
        ->input('reply-input', 'Hold on')
        ->tap('send-reply')
        ->assertSee('pending')
        ->assertNativeCalled('Dialog.Toast', fn (array $params): bool => $params['message'] === "[reply r9] pending: only you can see it until it's approved.");
});

it('shows validation messages from the server', function () {
    fakeThread(extra: ['POST /api/v1/commits' => [['message' => 'The body field must not be greater than 280 characters.', 'errors' => ['body' => ['The body field must not be greater than 280 characters.']]], 422]]);

    Native::visit('/commits/c2')
        ->input('reply-input', 'Long enough')
        ->tap('send-reply')
        ->assertSee('The body field must not be greater than 280 characters.')
        ->assertSet('reply', 'Long enough');
});

it('turns the budget red at zero and holds back a short reply', function () {
    fakeThread();

    Native::visit('/commits/c2')
        ->input('reply-input', 'hi')
        ->tap('send-reply')
        ->input('reply-input', str_repeat('a', 280))
        ->assertElement('text', fn (array $node): bool => ($node['ref'] ?? null) === 'reply-budget'
            && ($node['text'] ?? $node['props']['text'] ?? null) === '0'
            && ($node['props']['color'] ?? null) === config('native-ui.theme.light.destructive'));

    Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
});

it('deletes your own commit after a confirm', function () {
    fakeThread(extra: ['DELETE /api/v1/commits/c2' => [null, 204]]);

    $screen = Native::visit('/commits/c2')
        ->call('confirmDelete')
        ->assertNativeCalled('Dialog.Alert', fn (array $params): bool => $params['title'] === 'Delete this commit?')
        ->emitNative(ButtonPressed::class, ['index' => 0, 'label' => 'Cancel', 'id' => 'delete-commit'])
        ->assertNoNavigation();

    Http::assertNotSent(fn (Request $request): bool => $request->method() === 'DELETE');

    $screen->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Delete', 'id' => 'delete-commit'])
        ->assertNativeCalled('Dialog.Toast', fn (array $params): bool => $params['message'] === 'rm c2 → /dev/null')
        ->assertWentBack();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && $request->url() === 'http://devnull.test/api/v1/commits/c2');
});

it('offers delete only on your own commits', function () {
    fakeThread(['user' => userJson(['username' => 'ada'])], extra: ['DELETE /api/v1/commits/c2' => [null, 204]]);

    Native::visit('/commits/c2')
        ->call('delete')
        ->assertNoNavigation();

    Http::assertNotSent(fn (Request $request): bool => $request->method() === 'DELETE');
});

it('opens ancestors, replies and profiles', function () {
    fakeThread(replies: [commitJson(['id' => 'r1'])]);

    Native::visit('/commits/c2')->tap('commit-c1')->assertNavigatedTo('/commits/c1');
    Native::visit('/commits/c2')->tap('reply-r1')->assertNavigatedTo('/commits/r1');
    Native::visit('/commits/c2')->call('openProfile', 'ada')->assertNavigatedTo('/users/ada');
});

it('keeps deleted ancestors on the rail as tombstones', function () {
    fakeApi([
        'GET /api/v1/commits/c2' => [[
            'data' => commitJson(['id' => 'c2', 'parent_id' => 'c1', 'body' => 'The focused commit']),
            'ancestors' => [
                commitJson(['id' => 'c0', 'body' => 'The root commit', 'user' => userJson(['username' => 'ada', 'lane' => 1])]),
                tombstoneJson(['id' => 'c1', 'parent_id' => 'c0']),
            ],
        ]],
        'GET /api/v1/commits/c2/replies' => [pageJson([])],
    ]);

    $rail = config('native-ui.theme.light.rail');

    $screen = Native::visit('/commits/c2')
        ->assertSee('The root commit')
        ->assertSee('[deleted]')
        ->assertSee('This commit was deleted.')
        ->assertSee('The focused commit')
        ->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'commit-c1');

    // A dashed Rail Gray ring where the avatar was, as on the web, and a Rail Gray segment running on from it.
    $tombstone = nodeByRef($screen, 'tombstone-c1');
    $dashes = findNodes($tombstone, fn (array $node): bool => isset($node['props']['rotate']));

    expect($dashes)->toHaveCount(16)
        ->and(array_unique(array_map(fn (array $dash): ?string => $dash['style']['bg_color'] ?? null, $dashes)))->toBe([$rail])
        ->and(findNode($tombstone, fn (array $node): bool => ($node['type'] ?? null) === 'stack' && ($node['layout']['width'] ?? null) === 40.0))->toBeNull();
});

it('keeps deleted replies as tombstones and opens their replies in place', function () {
    fakeThread(replies: [
        tombstoneJson(['id' => 'r1', 'parent_id' => 'c2', 'replies_count' => 1]),
        commitJson(['id' => 'r2', 'parent_id' => 'c2', 'body' => 'Still here']),
    ], extra: [
        'GET /api/v1/commits/r1/replies' => [pageJson([commitJson(['id' => 'r3', 'parent_id' => 'r1', 'body' => 'Answering the deleted one'])])],
    ]);

    $screen = Native::visit('/commits/c2')
        ->assertSee('This commit was deleted.')
        ->assertSee('Still here')
        ->assertDontSee('Answering the deleted one')
        ->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'reply-r1')
        ->tap('tombstone-replies-r1')
        ->assertSee('Answering the deleted one')
        ->assertSee('Hide replies');

    $screen->tap('reply-r3')->assertNavigatedTo('/commits/r3');

    Native::visit('/commits/c2')
        ->tap('tombstone-replies-r1')
        ->tap('tombstone-replies-r1')
        ->assertDontSee('Answering the deleted one')
        ->assertSee('1 reply');
});

it('draws code blocks as mono text in a hairline box, from the body rather than the HTML', function () {
    $body = "Fixed it, @ada:\n```php\nreturn \$a <=> \$b;\n```\nShip it";

    fakeThread([
        'body' => $body,
        'body_segments' => [
            ['type' => 'text', 'text' => 'Fixed it, @ada:'],
            ['type' => 'block', 'language' => 'php', 'html' => '<span class="hl-keyword">return</span> $a &lt;=&gt; $b;'],
            ['type' => 'text', 'text' => 'Ship it'],
        ],
        'mentions' => [['username' => 'ada', 'offset' => 10, 'length' => 4]],
    ]);

    $screen = Native::visit('/commits/c2')
        ->assertSee('return $a <=> $b;')
        ->assertSee('php')
        ->assertSee('Ship it')
        ->assertDontSee('hl-keyword')
        ->assertElement('text', fn (array $node): bool => ($node['props']['text'] ?? null) === 'return $a <=> $b;'
            && ($node['props']['font_name'] ?? null) === 'mono');

    expect(paragraphRuns($screen, 'Fixed it'))->toBe([['Fixed it, '], ['@ada', 'mention'], [':']]);

    $screen->press("openProfile('ada')")->assertNavigatedTo('/users/ada');
});

it('shows the focused commit with its media and stars', function () {
    fakeThread([
        'stars_count' => 2,
        'link_preview' => ['url' => 'https://nativephp.com', 'title' => 'NativePHP', 'description' => null, 'image_url' => null, 'site_name' => 'NativePHP'],
    ]);

    Native::visit('/commits/c2')
        ->assertSee('NativePHP')
        ->assertElement('row', fn (array $node): bool => ($node['ref'] ?? null) === 'stars-c2' && ($node['props']['a11y_label'] ?? null) === 'Not starred, 2 stars')
        // The reply composer is right there, so the focused commit has no Reply action of its own.
        ->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'reply-to-c2')
        ->tap('link-preview-c2')
        ->assertNativeCalled('Browser.OpenInApp', fn (array $params): bool => $params['url'] === 'https://nativephp.com');
});

it('goes back to onboarding when the key stops working', function () {
    fakeApi(['GET /api/v1/commits/c2' => [['message' => 'This SSH key is not on the GitHub account of a devnull user.'], 401]]);

    Native::visit('/commits/c2')->assertReplacedWith('/onboarding');

    expect(app(Account::class)->user())->toBeNull();
});

it('reloads when you come back from a reply you deleted', function () {
    $replyDeleted = false;

    Http::fake(function (Request $request) use (&$replyDeleted) {
        $path = parse_url($request->url(), PHP_URL_PATH);

        return match (true) {
            $request->method() === 'DELETE' => tap(Http::response(null, 204), function () use (&$replyDeleted): void {
                $replyDeleted = true;
            }),
            $path === '/api/v1/commits/c2/replies' => Http::response(pageJson($replyDeleted ? [] : [commitJson(['id' => 'r1', 'parent_id' => 'c2', 'body' => 'Soon gone'])])),
            $path === '/api/v1/commits/c2' => Http::response(['data' => commitJson(['id' => 'c2', 'body' => 'Parent']), 'ancestors' => []]),
            $path === '/api/v1/commits/r1' => Http::response(['data' => commitJson(['id' => 'r1', 'parent_id' => 'c2', 'body' => 'Soon gone']), 'ancestors' => [commitJson(['id' => 'c2', 'body' => 'Parent'])]]),
            default => Http::response(pageJson([])),
        };
    });

    $reply = Native::visit('/commits/c2')
        ->assertSee('Soon gone')
        ->tap('reply-r1')
        ->follow()
        ->call('delete')
        ->assertWentBack();

    $reply->goBack()
        ->assertSee('Parent')
        ->assertDontSee('Soon gone')
        ->assertSee('No replies yet.');
});

it('shows your avatar once when you reply to yourself', function () {
    fakeApi([
        'GET /api/v1/commits/c3' => [[
            'data' => commitJson(['id' => 'c3', 'parent_id' => 'c2', 'body' => 'And one more thing']),
            'ancestors' => [
                commitJson(['id' => 'c1', 'body' => 'Started it', 'user' => userJson(['id' => 'u2', 'username' => 'ada', 'name' => 'Ada'])]),
                commitJson(['id' => 'c2', 'parent_id' => 'c1', 'body' => 'Replying to Ada']),
            ],
        ]],
        'GET /api/v1/commits/c3/replies' => [pageJson([])],
    ]);

    $screen = Native::visit('/commits/c3')
        ->assertSee('And one more thing');

    expect(railNode($screen, 'commit-c1'))->toBe('avatar')
        ->and(railNode($screen, 'commit-c2'))->toBe('avatar')
        ->and(railNode($screen, 'focused-commit'))->toBe('dot');
});
