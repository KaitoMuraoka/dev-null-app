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

it('goes back to onboarding when the key stops working', function () {
    fakeApi(['GET /api/v1/commits/c2' => [['message' => 'This SSH key is not on the GitHub account of a devnull user.'], 401]]);

    Native::visit('/commits/c2')->assertReplacedWith('/onboarding');

    expect(app(Account::class)->user())->toBeNull();
});
