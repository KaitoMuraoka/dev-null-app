<?php

use App\Devnull\Client;
use App\Devnull\Data\Commit;
use App\Devnull\DeviceKey;
use App\Devnull\Exceptions\ApiException;
use App\Devnull\Exceptions\KeychainUnavailable;
use App\Devnull\Exceptions\Unauthenticated;
use App\Devnull\RequestSigner;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

beforeEach(function () {
    config(['devnull.url' => 'http://devnull.test']);
    fakeKeychain(testSeed());
    $this->travelTo(now()->setTimestamp(1760000000));
});

/**
 * The message the server rebuilds from a request it received.
 */
function receivedMessage(Request $request): string
{
    $uri = $request->toPsrRequest()->getUri();
    $target = $uri->getPath().($uri->getQuery() === '' ? '' : '?'.$uri->getQuery());

    return RequestSigner::message(
        $request->header('X-Devnull-Timestamp')[0],
        $request->header('X-Devnull-Nonce')[0],
        $request->method(),
        $target,
        $request->body(),
    );
}

it('signs a GET with the device key, the query string as sent and an empty body', function () {
    Http::fake(['devnull.test/*' => Http::response(pageJson([commitJson()], 'next+page/1='))]);

    $page = app(Client::class)->log('everyone', 'abc def=');

    expect($page->items)->toHaveCount(1)
        ->and($page->items[0])->toBeInstanceOf(Commit::class)
        ->and($page->nextCursor)->toBe('next+page/1=');

    Http::assertSent(function (Request $request): bool {
        expect($request->url())->toBe('http://devnull.test/api/v1/log/everyone?cursor=abc%20def%3D')
            ->and($request->method())->toBe('GET')
            ->and($request->body())->toBe('')
            ->and($request->hasHeader('Content-Type'))->toBeFalse()
            ->and($request->header('Accept'))->toBe(['application/json'])
            ->and($request->header('X-Devnull-Timestamp'))->toBe(['1760000000'])
            ->and($request->header('X-Devnull-Nonce')[0])->toMatch('/^[0-9a-f]{32}$/')
            ->and(receivedMessage($request))->toStartWith("1760000000\n")
            ->toEndWith("\nGET\n/api/v1/log/everyone?cursor=abc%20def%3D\n")
            ->and($request->header('X-SSH-Signature')[0])->toBe(testKey()->sign(receivedMessage($request), 'devnull-api'));

        return true;
    });
});

it('leaves the cursor off the first page', function () {
    Http::fake(['devnull.test/*' => Http::response(pageJson([]))]);

    app(Client::class)->notifications();

    Http::assertSent(fn (Request $request): bool => $request->url() === 'http://devnull.test/api/v1/notifications');
});

it('sends the JSON body as the exact bytes it signed', function () {
    Http::fake(['devnull.test/*' => Http::response(['data' => commitJson(['status' => 'pending'])], 201)]);

    $commit = app(Client::class)->createCommit('ship it / "now" — ü', 'parent-1');

    expect($commit->isPending())->toBeTrue();

    Http::assertSent(function (Request $request): bool {
        expect($request->body())->toBe('{"body":"ship it / \"now\" — ü","parent_id":"parent-1"}')
            ->and($request->header('Content-Type'))->toBe(['application/json'])
            ->and(receivedMessage($request))->toEndWith("\nPOST\n/api/v1/commits\n".'{"body":"ship it / \"now\" — ü","parent_id":"parent-1"}')
            ->and($request->header('X-SSH-Signature')[0])->toBe(testKey()->sign(receivedMessage($request), 'devnull-api'));

        return true;
    });
});

it('uses a fresh nonce for every request', function () {
    Http::fake(['devnull.test/*' => Http::response(['data' => userJson()])]);

    app(Client::class)->me();
    app(Client::class)->me();

    $nonces = Http::recorded()->map(fn (array $pair): string => $pair[0]->header('X-Devnull-Nonce')[0]);

    expect($nonces->unique())->toHaveCount(2);
});

it('signs bodiless deletes and posts', function (string $call, string $method, string $target) {
    Http::fake(['devnull.test/*' => Http::response(null, 204)]);

    app(Client::class)->{$call}('local_dev');

    Http::assertSent(function (Request $request) use ($method, $target): bool {
        expect($request->method())->toBe($method)
            ->and($request->body())->toBe('')
            ->and($request->hasHeader('Content-Type'))->toBeFalse()
            ->and(receivedMessage($request))->toEndWith("\n{$method}\n{$target}\n");

        return true;
    });
})->with([
    ['follow', 'POST', '/api/v1/users/local_dev/follow'],
    ['unfollow', 'DELETE', '/api/v1/users/local_dev/follow'],
    ['deleteCommit', 'DELETE', '/api/v1/commits/local_dev'],
]);

it('signs the path prefix when devnull lives under one', function () {
    config(['devnull.url' => 'https://example.test/devnull/']);
    Http::fake(['example.test/*' => Http::response(['data' => userJson()])]);

    app(Client::class)->me();

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://example.test/devnull/api/v1/me'
        && str_contains(receivedMessage($request), "\nGET\n/devnull/api/v1/me\n"));
});

it('maps a 401 to Unauthenticated with the server message', function () {
    Http::fake(['devnull.test/*' => Http::response(['message' => 'This key is not on any devnull account.'], 401)]);

    expect(fn () => app(Client::class)->me())->toThrow(Unauthenticated::class, 'This key is not on any devnull account.');
});

it('keeps the server message for other errors', function () {
    Http::fake(['devnull.test/*' => Http::response(['message' => 'No GitHub user called ghost.'], 404)]);

    try {
        app(Client::class)->syncKey('ghost');
        $this->fail('Expected an ApiException.');
    } catch (ApiException $exception) {
        expect($exception)->not->toBeInstanceOf(Unauthenticated::class)
            ->and($exception->status)->toBe(404)
            ->and($exception->getMessage())->toBe('No GitHub user called ghost.');
    }
});

it('carries validation messages from a 422', function () {
    Http::fake(['devnull.test/*' => Http::response(['message' => 'The body field must be at least 3 characters.', 'errors' => ['body' => ['The body field must be at least 3 characters.']]], 422)]);

    try {
        app(Client::class)->createCommit('hi');
        $this->fail('Expected an ApiException.');
    } catch (ApiException $exception) {
        expect($exception->status)->toBe(422)
            ->and($exception->errors)->toBe(['body' => ['The body field must be at least 3 characters.']])
            ->and($exception->firstError())->toBe('The body field must be at least 3 characters.');
    }
});

it('speaks git when the server says nothing useful', function () {
    Http::fake(['devnull.test/*' => Http::response('<html>Bad Gateway</html>', 502)]);

    expect(fn () => app(Client::class)->me())->toThrow(ApiException::class, 'fatal: the remote end hung up unexpectedly (HTTP 502)');
});

it('reports an unreachable server', function () {
    Http::fake(fn () => throw new ConnectionException('Could not resolve host'));

    expect(fn () => app(Client::class)->me())->toThrow(ApiException::class, "fatal: unable to access 'http://devnull.test': could not connect to the server");
});

it('makes the device key on first use and keeps it in the Keychain', function () {
    $bridge = fakeKeychain();
    Http::fake(['devnull.test/*' => Http::response(['data' => userJson()])]);

    app(Client::class)->me();
    $key = app(DeviceKey::class)->current();

    $bridge->assertCalledTimes('SecureStorage.Set', 1);
    $bridge->assertCalled('SecureStorage.Set', fn (array $params): bool => $params['key'] === DeviceKey::StorageKey
        && base64_decode($params['value']) === $key->seed());
});

it('never replaces a key it cannot read', function () {
    $bridge = Native::fakeBridge()->respondTo('SecureStorage.Get', ['status' => 'unavailable', 'code' => 'UNAVAILABLE', 'message' => 'Device is locked.']);

    expect(fn () => app(DeviceKey::class)->current())->toThrow(KeychainUnavailable::class);

    $bridge->assertNotCalled('SecureStorage.Set');
});

it('reads commits, threads, profiles and notifications into data objects', function () {
    Http::fake([
        'devnull.test/api/v1/commits/c1' => Http::response([
            'data' => commitJson(['id' => 'c1', 'parent_id' => 'p1', 'user_id' => 'u9', 'user' => ['username' => 'ada', 'name' => null]]),
            'ancestors' => [commitJson(['id' => 'p1'])],
        ]),
        'devnull.test/api/v1/users/ada' => Http::response(['data' => [...userJson(['username' => 'ada']), 'commits_count' => 3, 'followers_count' => 5, 'following_count' => 1], 'is_following' => true, 'activity' => []]),
        'devnull.test/api/v1/notifications' => Http::response(pageJson([
            ['id' => 'n1', 'type' => 'replied', 'read_at' => null, 'created_at' => now()->toIso8601String(), 'actor' => userJson(), 'commit' => ['id' => 'c2', 'body' => 'Nice.', 'parent' => ['body' => 'Shipped.']]],
            ['id' => 'n2', 'type' => 'followed', 'read_at' => now()->toIso8601String(), 'actor' => userJson(), 'commit' => null],
        ])),
    ]);

    $thread = app(Client::class)->commit('c1');
    $profile = app(Client::class)->profile('ada');
    $notifications = app(Client::class)->notifications();

    expect($thread->commit->id)->toBe('c1')
        ->and($thread->commit->user->id)->toBe('u9')
        ->and($thread->commit->user->name)->toBe('ada')
        ->and($thread->ancestors[0]->id)->toBe('p1')
        ->and($profile->user->username)->toBe('ada')
        ->and($profile->commitsCount)->toBe(3)
        ->and($profile->followersCount)->toBe(5)
        ->and($profile->isFollowing)->toBeTrue()
        ->and($notifications->items[0]->isReply())->toBeTrue()
        ->and($notifications->items[0]->isUnread)->toBeTrue()
        ->and($notifications->items[0]->parentBody)->toBe('Shipped.')
        ->and($notifications->items[1]->isReply())->toBeFalse()
        ->and($notifications->items[1]->isUnread)->toBeFalse();
});

it('tolerates missing optional fields', function () {
    $commit = Commit::fromArray(['id' => 'c1', 'body' => 'Hello', 'user' => ['username' => 'ada']]);

    expect($commit->status)->toBe('approved')
        ->and($commit->createdAt)->toBeNull()
        ->and($commit->repliesCount)->toBe(0)
        ->and($commit->url)->toBeNull()
        ->and($commit->user->avatarUrl)->toBeNull()
        ->and($commit->user->id)->toBe('ada')
        ->and($commit->isDeleted())->toBeFalse()
        ->and($commit->starsCount)->toBe(0)
        ->and($commit->starred)->toBeFalse()
        ->and($commit->isSaved)->toBeNull()
        ->and($commit->isPinned)->toBeFalse()
        ->and($commit->mentions)->toBe([])
        ->and($commit->linkPreview)->toBeNull()
        ->and($commit->images)->toBe([])
        ->and($commit->parent)->toBeNull()
        ->and($commit->content)->toHaveCount(1);
});

it('reads a tombstone without a body or author', function () {
    $commit = Commit::fromArray(tombstoneJson(['id' => 'gone', 'parent_id' => 'p1', 'replies_count' => 2]));

    expect($commit->isDeleted())->toBeTrue()
        ->and($commit->id)->toBe('gone')
        ->and($commit->parentId)->toBe('p1')
        ->and($commit->repliesCount)->toBe(2)
        ->and($commit->body)->toBe('')
        ->and($commit->content)->toBe([])
        ->and($commit->lane())->toBe('rail');
});

it('reads what the web shows on a commit', function () {
    $commit = Commit::fromArray(commitJson([
        'stars_count' => 2,
        'starred' => true,
        'is_saved' => true,
        'is_pinned' => true,
        'link_preview' => ['url' => 'https://www.laravel.com/docs', 'title' => 'Docs', 'description' => '', 'image_url' => null, 'site_name' => null],
        'images' => [['id' => 'i1', 'url' => 'https://img.test/1', 'width' => 1080, 'height' => 2400], ['url' => null]],
        'parent' => tombstoneJson(),
    ]));

    expect($commit->starsCount)->toBe(2)
        ->and($commit->starred)->toBeTrue()
        ->and($commit->isSaved)->toBeTrue()
        ->and($commit->isPinned)->toBeTrue()
        ->and($commit->linkPreview?->site())->toBe('laravel.com')
        ->and($commit->linkPreview?->description)->toBeNull()
        ->and($commit->images)->toHaveCount(1)
        // A tall screenshot is held at 3:4, as the web caps an image's height.
        ->and($commit->images[0]->ratio())->toBe(0.75)
        ->and($commit->parent?->isDeleted())->toBeTrue();
});
