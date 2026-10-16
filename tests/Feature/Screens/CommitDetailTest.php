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
        'GET /api/v1/commits/c2' => [commitPageJson(
            commitJson(['id' => 'c2', 'parent_id' => 'c1', 'body' => 'The focused commit', ...$commit]),
            [commitJson(['id' => 'c1', 'body' => 'The parent commit', 'author' => userJson(['id' => 'u2', 'username' => 'ada', 'name' => 'Ada'])])],
        )],
        'GET /api/v1/commits/c2/replies' => [pageJson($replies)],
        ...$extra,
    ]);
}

it('shows the ancestors above the commit and the replies below', function () {
    fakeThread(replies: [replyJson(['id' => 'r1', 'parent_id' => 'c2', 'body' => 'First reply', 'author' => userJson(['username' => 'grace', 'name' => 'Grace'])])]);

    Native::visit('/log/commits/c2')
        ->assertNavTitle('Commit')
        ->assertSee('The parent commit')
        ->assertSee('The focused commit')
        ->assertSee('First reply')
        ->assertSee('Reply to @local_dev');
});

it("hangs the replies under the commit on the web's thread, the rail ending in a dot above them", function () {
    fakeThread(replies: [
        replyJson(['id' => 'r1', 'parent_id' => 'c2', 'body' => 'First reply', 'author' => userJson(['id' => 'u3', 'username' => 'grace', 'name' => 'Grace', 'lane' => 4])]),
        replyJson(['id' => 'r2', 'parent_id' => 'c2', 'body' => 'Second reply']),
    ]);

    $screen = Native::visit('/log/commits/c2', platform: 'ios');
    $first = nodeByRef($screen, 'thread-reply-r1');
    $second = nodeByRef($screen, 'thread-reply-r2');

    expect(findNode($first, fn (array $node): bool => ($node['ref'] ?? null) === 'thread-elbow'))->not->toBeNull()
        ->and(findNode($second, fn (array $node): bool => ($node['ref'] ?? null) === 'thread-elbow'))->toBeNull()
        ->and(findNode(nodeByRef($screen, 'focused-commit'), fn (array $node): bool => ($node['ref'] ?? null) === 'end-dot'))->not->toBeNull();
});

it('prints the date the way git log does', function () {
    fakeThread(['created_at' => '2026-10-14T20:13:05Z']);

    Native::visit('/log/commits/c2')->assertSee('Wed Oct 14 20:13:05 2026 +0000 · c2');
});

it('posts a reply, with no countdown while there is room', function () {
    fakeThread(extra: ['POST /api/v1/commits' => [['data' => commitJson(['id' => 'r9', 'parent_id' => 'c2', 'body' => 'A fresh reply'])], 201]]);

    Native::visit('/log/commits/c2')
        ->assertSee('No replies yet.')
        ->input('reply-input', 'A fresh reply')
        ->assertMissingElement('text', fn (array $node): bool => ($node['ref'] ?? null) === 'reply-budget')
        ->tap('send-reply')
        ->assertSet('reply', '')
        ->assertSee('A fresh reply')
        ->assertNativeNotCalled('Dialog.Toast');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->body() === '{"body":"A fresh reply","parent_id":"c2"}');
});

it('marks a reply waiting for approval as pending on its row', function () {
    fakeThread(extra: ['POST /api/v1/commits' => [['data' => commitJson(['id' => 'r9', 'status' => 'pending', 'body' => 'Hold on'])], 201]]);

    Native::visit('/log/commits/c2')
        ->input('reply-input', 'Hold on')
        ->tap('send-reply')
        ->assertSee('pending')
        ->assertNativeNotCalled('Dialog.Toast');
});

it('shows validation messages from the server', function () {
    fakeThread(extra: ['POST /api/v1/commits' => [['message' => 'The body field must not be greater than 280 characters.', 'errors' => ['body' => ['The body field must not be greater than 280 characters.']]], 422]]);

    Native::visit('/log/commits/c2')
        ->input('reply-input', 'Long enough')
        ->tap('send-reply')
        ->assertSee('The body field must not be greater than 280 characters.')
        ->assertSet('reply', 'Long enough');
});

it('turns the budget red at zero and holds back a short reply', function () {
    fakeThread();

    Native::visit('/log/commits/c2')
        ->input('reply-input', 'hi')
        ->tap('send-reply')
        ->input('reply-input', str_repeat('a', 4000))
        ->assertElement('text', fn (array $node): bool => ($node['ref'] ?? null) === 'reply-budget'
            && ($node['text'] ?? $node['props']['text'] ?? null) === '0'
            && ($node['props']['color'] ?? null) === config('native-ui.theme.light.destructive'));

    Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
});

it('deletes your own commit after a confirm', function () {
    fakeThread(extra: ['DELETE /api/v1/commits/c2' => [null, 204]]);

    $screen = Native::visit('/log/commits/c2')
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

it('draws the delete action with the web\'s trash icon', function (string $platform) {
    fakeThread();

    $action = findNode(Native::visit('/log/commits/c2', platform: $platform)->tree(), fn (array $node): bool => ($node['type'] ?? null) === 'top_bar_action' && ($node['props']['id'] ?? null) === 'delete');

    expect($action['props'] ?? null)->toMatchArray(['icon' => 'custom:trash-2', 'a11y_label' => 'Delete commit'])
        ->not->toHaveKey('material_variant');
})->with(['ios', 'android']);

it('offers delete only on your own commits', function () {
    fakeThread(['author' => userJson(['username' => 'ada'])], extra: ['DELETE /api/v1/commits/c2' => [null, 204]]);

    Native::visit('/log/commits/c2')
        ->call('delete')
        ->assertNoNavigation();

    Http::assertNotSent(fn (Request $request): bool => $request->method() === 'DELETE');
});

it('opens ancestors, replies and profiles', function () {
    fakeThread(replies: [replyJson(['id' => 'r1'])]);

    Native::visit('/log/commits/c2')->tap('commit-c1')->assertNavigatedTo('/log/commits/c1');
    Native::visit('/log/commits/c2')->tap('thread-reply-r1')->assertNavigatedTo('/log/commits/r1');
    Native::visit('/log/commits/c2')->call('openProfile', 'ada')->assertNavigatedTo('/log/users/ada');
});

it('keeps deleted ancestors on the rail as tombstones', function () {
    fakeApi([
        'GET /api/v1/commits/c2' => [commitPageJson(
            commitJson(['id' => 'c2', 'parent_id' => 'c1', 'body' => 'The focused commit']),
            [
                commitJson(['id' => 'c0', 'body' => 'The root commit', 'author' => userJson(['username' => 'ada', 'lane' => 1])]),
                tombstoneJson(['id' => 'c1', 'parent_id' => 'c0']),
            ],
        )],
        'GET /api/v1/commits/c2/replies' => [pageJson([])],
    ]);

    $rail = config('native-ui.theme.light.rail');

    $screen = Native::visit('/log/commits/c2')
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
        replyJson(['id' => 'r2', 'parent_id' => 'c2', 'body' => 'Still here']),
    ], extra: [
        'GET /api/v1/commits/r1/replies' => [pageJson([replyJson(['id' => 'r3', 'parent_id' => 'r1', 'body' => 'Answering the deleted one'])])],
    ]);

    $screen = Native::visit('/log/commits/c2')
        ->assertSee('This commit was deleted.')
        ->assertSee('Still here')
        ->assertDontSee('Answering the deleted one')
        ->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'thread-reply-r1')
        ->tap('tombstone-replies-r1')
        ->assertSee('Answering the deleted one')
        ->assertSee('Hide replies');

    // One level in, on an elbow off the deleted reply's spine.
    expect(findNode(nodeByRef($screen, 'thread-reply-r3'), fn (array $node): bool => ($node['ref'] ?? null) === 'thread-elbow'))->not->toBeNull();

    $screen->tap('thread-reply-r3')->assertNavigatedTo('/log/commits/r3');

    Native::visit('/log/commits/c2')
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

    $screen = Native::visit('/log/commits/c2')
        ->assertSee('return $a <=> $b;')
        ->assertSee('php')
        ->assertSee('Ship it')
        ->assertDontSee('hl-keyword')
        ->assertElement('text', fn (array $node): bool => ($node['props']['text'] ?? null) === 'return $a <=> $b;'
            && ($node['props']['font_name'] ?? null) === 'mono');

    expect(paragraphRuns($screen, 'Fixed it'))->toBe([['Fixed it, '], ['@ada', 'mention'], [':']]);

    $screen->press("openProfile('ada')")->assertNavigatedTo('/log/users/ada');
});

it('shows the focused commit with its media and stars', function () {
    fakeThread([
        'author' => userJson(['id' => 'u3', 'username' => 'grace', 'name' => 'Grace']),
        'stars_count' => 2,
        'link_preview' => ['url' => 'https://nativephp.com', 'title' => 'NativePHP', 'description' => null, 'image_url' => null, 'site_name' => 'NativePHP'],
    ]);

    Native::visit('/log/commits/c2')
        ->assertSee('NativePHP')
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'star-c2' && ($node['props']['a11y_label'] ?? null) === 'Star commit, 2 stars')
        // The reply composer is right there, so the focused commit has no Reply action of its own.
        ->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'reply-to-c2')
        ->tap('link-preview-c2')
        ->assertNativeCalled('Browser.OpenInApp', fn (array $params): bool => $params['url'] === 'https://nativephp.com');
});

it('goes back to onboarding when the key stops working', function () {
    fakeApi(['GET /api/v1/commits/c2' => [['message' => 'This SSH key is not on the GitHub account of a devnull user.'], 401]]);

    Native::visit('/log/commits/c2')->assertReplacedWith('/onboarding');

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
            $path === '/api/v1/commits/c2/replies' => Http::response(pageJson($replyDeleted ? [] : [replyJson(['id' => 'r1', 'parent_id' => 'c2', 'body' => 'Soon gone'])])),
            $path === '/api/v1/commits/c2' => Http::response(commitPageJson(commitJson(['id' => 'c2', 'body' => 'Parent']))),
            $path === '/api/v1/commits/r1' => Http::response(commitPageJson(commitJson(['id' => 'r1', 'parent_id' => 'c2', 'body' => 'Soon gone']), [commitJson(['id' => 'c2', 'body' => 'Parent'])])),
            default => Http::response(pageJson([])),
        };
    });

    $reply = Native::visit('/log/commits/c2')
        ->assertSee('Soon gone')
        ->tap('thread-reply-r1')
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
        'GET /api/v1/commits/c3' => [commitPageJson(
            commitJson(['id' => 'c3', 'parent_id' => 'c2', 'body' => 'And one more thing']),
            [
                commitJson(['id' => 'c1', 'body' => 'Started it', 'author' => userJson(['id' => 'u2', 'username' => 'ada', 'name' => 'Ada'])]),
                commitJson(['id' => 'c2', 'parent_id' => 'c1', 'body' => 'Replying to Ada']),
            ],
        )],
        'GET /api/v1/commits/c3/replies' => [pageJson([])],
    ]);

    $screen = Native::visit('/log/commits/c3')
        ->assertSee('And one more thing');

    expect(railNode($screen, 'commit-c1'))->toBe('avatar')
        ->and(railNode($screen, 'commit-c2'))->toBe('avatar')
        ->and(railNode($screen, 'focused-commit'))->toBe('dot');
});

it('focuses the reply composer when opened from a Reply action', function () {
    fakeThread();

    $input = fn (array $node): bool => ($node['ref'] ?? null) === 'reply-input' && ($node['props']['autofocus'] ?? false) === true;

    Native::visit('/log/commits/c2', ['reply' => true])->assertElement('bare_text_input', $input);
    Native::visit('/log/commits/c2')->assertMissingElement('bare_text_input', $input);
});

it('stars the focused commit and its replies in place', function () {
    fakeThread(['author' => userJson(['id' => 'u3', 'username' => 'grace', 'name' => 'Grace'])], replies: [replyJson(['id' => 'r1', 'parent_id' => 'c2'])], extra: [
        'POST /api/v1/commits/c2/star' => [['data' => commitJson(['id' => 'c2', 'stars_count' => 1, 'starred' => true])]],
        'PUT /api/v1/commits/r1/bookmark' => [['data' => commitJson(['id' => 'r1', 'is_saved' => true])]],
    ]);

    $screen = Native::visit('/log/commits/c2')->tap('star-c2')->tap('bookmark-r1');

    expect($screen->instance()->commit->starred)->toBeTrue()
        ->and($screen->instance()->commit->body)->toBe('The focused commit')
        ->and($screen->instance()->replies[0]->isSaved)->toBeTrue();
});

it('lets a drag that starts on an ancestor or a reply scroll the thread', function () {
    fakeThread(replies: [replyJson(['id' => 'r1', 'parent_id' => 'c2'])]);

    $screen = Native::visit('/log/commits/c2', platform: 'ios');

    nodeByRef($screen, 'commit-c1');
    nodeByRef($screen, 'thread-reply-r1');

    expect(pressFeedbackInScroller($screen))->toBe([]);
});

it('paints the reply bar solid, with its hairline, so the replies never show through', function () {
    fakeThread();

    $screen = Native::visit('/log/commits/c2', platform: 'ios');
    $bar = findNode($screen->tree(), fn (array $node): bool => ($node['type'] ?? null) === 'bottom_bar');

    expect($bar['children'][0]['ref'] ?? null)->toBe('reply-bar')
        ->and($bar['children'][0]['style']['bg_color'] ?? null)->toBe(config('native-ui.theme.light.surface'))
        ->and($bar['children'][0]['props']['dark_bg_color'] ?? null)->toBe(config('native-ui.theme.dark.surface'))
        ->and($bar['children'][0]['children'][0]['style']['bg_color'] ?? null)->toBe(config('native-ui.theme.light.hairline'));
});

it('ends the page above the reply bar, and further as the draft grows', function () {
    fakeThread(replies: [replyJson(['id' => 'r1', 'parent_id' => 'c2', 'body' => 'The last reply'])]);

    $screen = Native::visit('/log/commits/c2', platform: 'ios');
    $scroller = findNode($screen->tree(), fn (array $node): bool => ($node['type'] ?? null) === 'scroll_view');
    $page = $scroller['children'][0]['children'];

    // The clearance is the last thing on the page, under the last reply: the bar's line and the home indicator.
    expect(end($page)['ref'] ?? null)->toBe('reply-bar-clearance')
        ->and(nodeByRef($screen, 'reply-bar-clearance')['layout']['height'])->toEqual(24 + 53 + 34);

    $screen->input('reply-input', "Three lines\nof a\ndraft");

    expect(nodeByRef($screen, 'reply-bar-clearance')['layout']['height'])->toEqual(24 + 53 + 2 * 21 + 34);

    $screen->input('reply-input', str_repeat("A line\n", 12));

    expect(nodeByRef($screen, 'reply-bar-clearance')['layout']['height'])->toEqual(24 + 53 + 4 * 21 + 34);
});
