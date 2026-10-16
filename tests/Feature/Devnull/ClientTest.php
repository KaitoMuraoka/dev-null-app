<?php

use App\Devnull\Client;
use App\Devnull\Data\Commit;
use App\Devnull\Data\PairingCode;
use App\Devnull\Data\User;
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

it('asks for a profile\'s replies with ?replies=1 and signs it', function () {
    Http::fake(['devnull.test/*' => Http::response(pageJson([]))]);

    app(Client::class)->userCommits('ada', 'replies', 'older');
    app(Client::class)->userCommits('ada');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'http://devnull.test/api/v1/users/ada/commits?replies=1&cursor=older'
        && str_ends_with(receivedMessage($request), "\nGET\n/api/v1/users/ada/commits?replies=1&cursor=older\n"));
    Http::assertSent(fn (Request $request): bool => $request->url() === 'http://devnull.test/api/v1/users/ada/commits');
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'tab='));
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

it('tells a phone that only needs approving from a key devnull does not know', function (string $message, bool $needsApproval) {
    Http::fake(['devnull.test/*' => Http::response(['message' => $message], 401)]);

    try {
        app(Client::class)->me();
        $this->fail('Expected Unauthenticated.');
    } catch (Unauthenticated $exception) {
        expect($exception->needsApproval())->toBe($needsApproval);
    }
})->with([
    'not approved' => ["This key isn't approved for the devnull app yet. Approve it on http://devnull.test/settings/devices.", true],
    'not on GitHub' => ['This SSH key (SHA256:abc) is not on the GitHub account of a devnull user.', false],
    'a bad signature' => ['Invalid signature.', false],
]);

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

it('sends the GitHub token and the device name with a key sync, signed', function () {
    Http::fake(['devnull.test/*' => Http::response(['data' => userWithCountsJson()])]);

    $user = app(Client::class)->syncKey('local_dev', 'ghu_not-a-real-token', 'devnull on iPhone');

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->username)->toBe('local_dev');

    Http::assertSent(function (Request $request): bool {
        $body = '{"username":"local_dev","github_token":"ghu_not-a-real-token","device_name":"devnull on iPhone"}';

        expect($request->url())->toBe('http://devnull.test/api/v1/key-syncs')
            ->and($request->body())->toBe($body)
            ->and($request->hasHeader('Authorization'))->toBeFalse()
            ->and(receivedMessage($request))->toEndWith("\nPOST\n/api/v1/key-syncs\n".$body)
            ->and($request->header('X-SSH-Signature')[0])->toBe(testKey()->sign(receivedMessage($request), 'devnull-api'));

        return true;
    });
});

it('leaves an empty token and device name out of a key sync', function () {
    Http::fake(['devnull.test/*' => Http::response(['data' => userWithCountsJson()])]);

    app(Client::class)->syncKey('local_dev', '', '');
    app(Client::class)->syncKey('local_dev');

    expect(Http::recorded()->map(fn (array $pair): string => $pair[0]->body())->all())
        ->toBe(['{"username":"local_dev"}', '{"username":"local_dev"}']);
});

it('reads the pairing code devnull answers with until the phone is approved', function () {
    Http::fake(['devnull.test/*' => Http::response([
        'message' => 'Enter ABCD-2345 on http://devnull.test/settings/devices to approve this device.',
        'pairing_code' => 'ABCD-2345',
        'approval_url' => 'http://devnull.test/settings/devices',
        'expires_at' => '2025-10-09T09:08:20Z',
    ], 202)]);

    $code = app(Client::class)->syncKey('local_dev', deviceName: 'devnull on iPhone');

    expect($code)->toBeInstanceOf(PairingCode::class)
        ->and($code->code)->toBe('ABCD-2345')
        ->and($code->approvalUrl)->toBe('http://devnull.test/settings/devices')
        ->and($code->message)->toBe('Enter ABCD-2345 on http://devnull.test/settings/devices to approve this device.')
        ->and($code->expiresAt->getTimestamp())->toBe(1760000900);
});

it('says so when a pairing code makes no sense', function () {
    Http::fake(['devnull.test/*' => Http::response(['message' => 'Accepted.'], 202)]);

    expect(fn () => app(Client::class)->syncKey('local_dev'))->toThrow(ApiException::class, 'fatal: devnull sent a pairing code this app could not read.');
});

it('reads how long a 429 asks to wait', function (string $retryAfter, ?int $seconds) {
    Http::fake(['devnull.test/*' => Http::response(['message' => 'Too Many Attempts.'], 429, $retryAfter === '' ? [] : ['Retry-After' => $retryAfter])]);

    try {
        app(Client::class)->syncKey('local_dev');
        $this->fail('Expected an ApiException.');
    } catch (ApiException $exception) {
        expect($exception->status)->toBe(429)
            ->and($exception->getMessage())->toBe('Too Many Attempts.')
            ->and($exception->retryAfter)->toBe($seconds);
    }
})->with([
    'in seconds' => ['42', 42],
    'as a date' => ['Thu, 09 Oct 2025 09:00:20 GMT', 420],
    'not at all' => ['', null],
]);

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
        'devnull.test/api/v1/commits/c1' => Http::response(commitPageJson(
            commitJson(['id' => 'c1', 'parent_id' => 'p1', 'author' => ['id' => 'u9', 'username' => 'ada', 'name' => null]]),
            [commitJson(['id' => 'p1'])],
        )),
        'devnull.test/api/v1/users/ada' => Http::response([
            'data' => userWithCountsJson(['username' => 'ada', 'lane' => 4], commits: 3, followers: 5, following: 1),
            'is_following' => true,
            'activity' => ['scale' => 'day', 'buckets' => [['date' => '2026-10-15T09:00:00Z', 'count' => 2]]],
            'streaks' => ['longest' => 4, 'current' => 1],
            'pinned_commit' => commitJson(['id' => 'p1', 'is_pinned' => true]),
        ]),
        'devnull.test/api/v1/notifications' => Http::response(pageJson([
            notificationJson('replied', ['commit' => notificationCommitJson(['id' => 'c2', 'parent_id' => 'c-yours', 'body' => 'Nice.', 'parent' => notificationCommitJson(['id' => 'c-yours', 'body' => 'Shipped.'], withParent: false)])]),
            notificationJson('followed', ['read_at' => now()->toIso8601ZuluString(), 'unread_count' => 0]),
        ], path: 'http://devnull.test/api/v1/notifications')),
    ]);

    $thread = app(Client::class)->commit('c1');
    $profile = app(Client::class)->profile('ada');
    $notifications = app(Client::class)->notifications();

    expect($thread->commit->id)->toBe('c1')
        ->and($thread->commit->author->id)->toBe('u9')
        ->and($thread->commit->author->name)->toBe('ada')
        ->and($thread->ancestors[0]->id)->toBe('p1')
        ->and($profile->user->username)->toBe('ada')
        ->and($profile->commitsCount)->toBe(3)
        ->and($profile->followersCount)->toBe(5)
        ->and($profile->isFollowing)->toBeTrue()
        ->and($profile->user->lane())->toBe(4)
        ->and($profile->activity->scale)->toBe('day')
        ->and($profile->activity->total())->toBe(2)
        ->and($profile->streaks->longest)->toBe(4)
        ->and($profile->streaks->current)->toBe(1)
        ->and($profile->pinnedCommit?->id)->toBe('p1')
        ->and($profile->pinnedCommit?->isPinned)->toBeTrue()
        ->and($notifications->items[0]->isReply())->toBeTrue()
        ->and($notifications->items[0]->isUnread)->toBeTrue()
        ->and($notifications->items[0]->parentBody)->toBe('Shipped.')
        ->and($notifications->items[1]->isReply())->toBeFalse()
        ->and($notifications->items[1]->isUnread)->toBeFalse();
});

it('tolerates missing optional fields', function () {
    $commit = Commit::fromArray(['id' => 'c1', 'body' => 'Hello', 'author' => ['username' => 'ada']]);

    expect($commit->status)->toBe('approved')
        ->and($commit->createdAt)->toBeNull()
        ->and($commit->repliesCount)->toBe(0)
        ->and($commit->htmlUrl)->toBeNull()
        ->and($commit->shortHash)->toBe('c1')
        ->and($commit->author->avatarUrl)->toBeNull()
        ->and($commit->author->id)->toBe('ada')
        ->and($commit->author->htmlUrl)->toBeNull()
        ->and($commit->author->githubUrl)->toBeNull()
        ->and($commit->author->createdAt)->toBeNull()
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

it('reads a commit\'s author, and its parent\'s, as full users', function () {
    $ada = userJson([
        'id' => 'u2',
        'username' => 'ada',
        'name' => 'Ada Lovelace',
        'bio' => null,
        'company' => 'Analytical Engines',
        'location' => 'London',
        'website' => 'https://ada.example',
        'github_url' => 'https://github.com/ada',
        'html_url' => 'https://dev-null.sh/@ada',
        'feed_url' => 'https://dev-null.sh/@ada/feed.atom',
        'created_at' => '2026-09-01T08:00:00Z',
        'lane' => 4,
    ]);

    $commit = Commit::fromArray(commitJson([
        'id' => '0199e5c4-1111-7000-8000-00000000c0de',
        'short_hash' => 'abc1234',
        'html_url' => 'https://dev-null.sh/@local_dev/commits/0199e5c4-1111-7000-8000-00000000c0de',
        'parent_id' => 'p1',
        'parent' => commitJson(['id' => 'p1', 'author' => $ada]),
    ]));

    expect($commit->author->username)->toBe('local_dev')
        ->and($commit->author->name)->toBe('Local Dev')
        ->and($commit->author->lane())->toBe(3)
        ->and($commit->author->githubUrl)->toBe('https://github.com/local_dev')
        // The API's own short hash and link, not ones worked out here.
        ->and($commit->shortHash)->toBe('abc1234')
        ->and($commit->htmlUrl)->toBe('https://dev-null.sh/@local_dev/commits/0199e5c4-1111-7000-8000-00000000c0de')
        ->and($commit->parent?->author->id)->toBe('u2')
        ->and($commit->parent?->author->username)->toBe('ada')
        ->and($commit->parent?->author->bio)->toBeNull()
        ->and($commit->parent?->author->company)->toBe('Analytical Engines')
        ->and($commit->parent?->author->location)->toBe('London')
        ->and($commit->parent?->author->website)->toBe('https://ada.example')
        ->and($commit->parent?->author->githubUrl)->toBe('https://github.com/ada')
        ->and($commit->parent?->author->htmlUrl)->toBe('https://dev-null.sh/@ada')
        ->and($commit->parent?->author->feedUrl)->toBe('https://dev-null.sh/@ada/feed.atom')
        ->and($commit->parent?->author->createdAt?->toIso8601ZuluString())->toBe('2026-09-01T08:00:00Z')
        ->and($commit->parent?->author->lane())->toBe(4);
});

it('keeps you between launches with everything devnull said about you', function () {
    $user = User::fromArray(userJson(['company' => 'devnull', 'website' => 'https://local.dev']));

    expect(User::fromArray($user->toArray()))->toEqual($user);
});

it('reads who you are and how many notifications are unread', function () {
    Http::fake(['devnull.test/api/v1/me' => Http::response(['data' => userWithCountsJson(['lane' => 2]), 'unread_notifications_count' => 7])]);

    $current = app(Client::class)->me();

    expect($current->user->username)->toBe('local_dev')
        ->and($current->user->lane())->toBe(2)
        ->and($current->unreadNotificationsCount)->toBe(7);
});
