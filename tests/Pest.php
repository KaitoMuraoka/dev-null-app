<?php

use App\Devnull\Account;
use App\Devnull\Data\User;
use App\Devnull\DeviceKey;
use App\Devnull\SshKey;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\FakeBridge;
use Native\Mobile\Testing\Native;
use Native\Mobile\Testing\TestableComponent;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Fakes the devnull API by "METHOD /path", ignoring the query string.
 * Each route answers with [body, status], built fresh for every request.
 * Anything else gets a 404, so a stray request shows up in the test.
 *
 * @param  array<string, array{0: mixed, 1?: int}>  $routes
 */
function fakeApi(array $routes): void
{
    Http::fake(function (Request $request) use ($routes) {
        $route = $request->method().' '.parse_url($request->url(), PHP_URL_PATH);

        if (! array_key_exists($route, $routes)) {
            return Http::response(['message' => "No fake for {$route}."], 404);
        }

        return Http::response($routes[$route][0], $routes[$route][1] ?? 200);
    });
}

/**
 * A Keychain held in memory, answering the SecureStorage bridge calls.
 */
function fakeKeychain(?string $seed = null): FakeBridge
{
    $store = $seed === null ? [] : [DeviceKey::StorageKey => base64_encode($seed)];

    return Native::fakeBridge()
        ->respondTo('SecureStorage.Get', function (array $params) use (&$store): array {
            return array_key_exists($params['key'], $store)
                ? ['status' => 'found', 'value' => $store[$params['key']]]
                : ['status' => 'not_found', 'value' => null];
        })
        ->respondTo('SecureStorage.Set', function (array $params) use (&$store): array {
            $store[$params['key']] = $params['value'];

            return ['success' => true];
        })
        ->respondTo('SecureStorage.Delete', function (array $params) use (&$store): array {
            unset($store[$params['key']]);

            return ['success' => true];
        });
}

function testSeed(): string
{
    return implode('', array_map(chr(...), range(0, 31)));
}

function testKey(): SshKey
{
    return SshKey::fromSeed(testSeed());
}

/**
 * A device whose key is in the Keychain and verified as the given user.
 *
 * @param  array<string, mixed>  $user
 */
function signIn(array $user = []): FakeBridge
{
    $bridge = fakeKeychain(testSeed());

    app(Account::class)->remember(User::fromArray(userJson($user)));

    return $bridge;
}

/**
 * A user as the API sends one to a signed request: a commit's author, a
 * notification's actor. Its links follow the username unless a test sends its own.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function userJson(array $overrides = []): array
{
    $username = $overrides['username'] ?? 'local_dev';

    return [
        'id' => '0199e5c4-0000-7000-8000-000000000001',
        'username' => $username,
        'name' => 'Local Dev',
        'avatar_url' => 'https://avatars.githubusercontent.com/u/1?v=4',
        'bio' => 'Ships things.',
        'company' => null,
        'location' => null,
        'website' => null,
        'github_url' => "https://github.com/{$username}",
        'html_url' => "http://devnull.test/@{$username}",
        'feed_url' => "http://devnull.test/@{$username}/feed.atom",
        'created_at' => '2026-10-15T15:43:47Z',
        'lane' => 3,
        ...$overrides,
    ];
}

/**
 * A user with their counts, as `/me`, a profile and a key sync send them.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function userWithCountsJson(array $overrides = [], int $commits = 1, int $followers = 0, int $following = 0): array
{
    $user = userJson($overrides);
    $lane = $user['lane'];
    unset($user['lane']);

    return [...$user, 'commits_count' => $commits, 'followers_count' => $followers, 'following_count' => $following, 'lane' => $lane];
}

/**
 * A commit as the API sends it to a signed request. The short hash and the link
 * follow the id and the author, and the body's segments follow the body, unless
 * a test sends its own, as the server always works them out from what it stores.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function commitJson(array $overrides = []): array
{
    $id = $overrides['id'] ?? '0199e5c4-1111-7000-8000-00000000c0de';
    $body = $overrides['body'] ?? 'Shipped the mobile app.';
    $author = $overrides['author'] ?? userJson();

    return [
        'id' => $id,
        'short_hash' => substr(str_replace('-', '', $id), -7),
        'body' => $body,
        'parent_id' => null,
        'html_url' => 'http://devnull.test/@'.($author['username'] ?? '').'/commits/'.$id,
        'created_at' => now()->subMinutes(5)->toIso8601ZuluString(),
        'stars_count' => 0,
        'replies_count' => 0,
        'link_preview' => null,
        'images' => [],
        'author' => $author,
        'body_segments' => $body === '' ? [] : [['type' => 'text', 'text' => $body]],
        'mentions' => [],
        'status' => 'approved',
        'series_id' => null,
        'series_position' => null,
        'series_size' => null,
        'is_pinned' => false,
        'starred' => false,
        'is_saved' => false,
        'deleted_at' => null,
        ...$overrides,
    ];
}

/**
 * A `GET /commits/{id}` response: the commit, what it replies to (or the earlier
 * parts of its series) and the later parts of its series.
 *
 * @param  array<string, mixed>  $commit
 * @param  list<array<string, mixed>>  $ancestors
 * @param  list<array<string, mixed>>  $series
 * @return array<string, mixed>
 */
function commitPageJson(array $commit, array $ancestors = [], array $series = []): array
{
    return ['data' => $commit, 'ancestors' => $ancestors, 'series' => $series];
}

/**
 * A reply as `GET /commits/{id}/replies` sends it: like any commit, but without
 * stars_count or starred, which that list doesn't load.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function replyJson(array $overrides = []): array
{
    return array_diff_key(commitJson($overrides), array_flip(['stars_count', 'starred']));
}

/**
 * A notification's commit, as the API sends it: without its counts, preview,
 * images or your star and bookmark, and with the commit it replies to when you
 * may see it. That parent comes without a parent of its own.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function notificationCommitJson(array $overrides = [], bool $withParent = true): array
{
    $commit = array_diff_key(commitJson($overrides), array_flip(['stars_count', 'replies_count', 'link_preview', 'images', 'starred', 'is_saved', 'parent']));

    return $withParent ? [...$commit, 'parent' => $overrides['parent'] ?? null] : $commit;
}

/**
 * A group of notifications as the API sends it: the newest one, the first few
 * people in the group, how many there are and how many are unread. Stars and
 * replies group on your commit (subject_id); everything else stands alone.
 * A moderation update names you as its actor, as devnull stores it.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function notificationJson(string $type, array $overrides = []): array
{
    $isModeration = in_array($type, ['approved', 'rejected'], true);
    $actor = $overrides['actor'] ?? ($isModeration ? userJson() : userJson(['id' => 'u2', 'username' => 'ada', 'name' => 'Ada', 'lane' => 2]));
    $yours = notificationCommitJson(['id' => 'c-yours', 'body' => 'Shipped the app.'], withParent: false);

    $commit = match ($type) {
        'followed' => null,
        'replied' => notificationCommitJson(['id' => 'c-replied', 'parent_id' => 'c-yours', 'body' => 'About replied', 'author' => $actor, 'parent' => $yours]),
        'starred' => notificationCommitJson(['id' => 'c-starred', 'body' => 'About starred']),
        'mentioned' => notificationCommitJson(['id' => 'c-mentioned', 'body' => 'About mentioned', 'author' => $actor]),
        default => notificationCommitJson(['id' => 'c-'.$type, 'body' => 'About '.$type, 'status' => $type]),
    };

    return [
        'id' => 'n-'.$type,
        'type' => $type,
        'subject_id' => match ($type) {
            'replied' => 'c-yours',
            'starred' => 'c-starred',
            default => null,
        },
        'reason' => null,
        'read_at' => null,
        'created_at' => now()->subHours(3)->toIso8601ZuluString(),
        'actor' => $actor,
        'actors' => [$actor],
        'actors_count' => 1,
        'unread_count' => 1,
        'commit' => $commit,
        ...$overrides,
    ];
}

/**
 * A deleted commit that still has replies, as the API sends it: no body, no author.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function tombstoneJson(array $overrides = []): array
{
    return [
        'id' => '0199e5c4-2222-7000-8000-0000000dead0',
        'parent_id' => null,
        'deleted_at' => now()->subHour()->toIso8601ZuluString(),
        'replies_count' => 1,
        ...$overrides,
    ];
}

/**
 * One page of a cursor-paginated list, as Laravel sends it. links.next carries
 * the list's own query, such as a profile's ?replies=1, ahead of the cursor.
 *
 * @param  list<array<string, mixed>>  $items
 * @return array<string, mixed>
 */
function pageJson(array $items, ?string $nextCursor = null, string $path = 'http://devnull.test/api/v1/log/following', string $query = '', int $perPage = 20): array
{
    return [
        'data' => $items,
        'links' => [
            'first' => null,
            'last' => null,
            'prev' => null,
            'next' => $nextCursor === null ? null : $path.'?'.($query === '' ? '' : $query.'&').'cursor='.$nextCursor,
        ],
        'meta' => ['path' => $path, 'per_page' => $perPage, 'next_cursor' => $nextCursor, 'prev_cursor' => null],
    ];
}

/**
 * GitHub's answer to `POST /login/device/code`, as its docs show it.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function gitHubDeviceCodeJson(array $overrides = []): array
{
    return [
        'device_code' => '3584d83530557fdd1f46af8289938c8ef79f9dc5',
        'user_code' => 'WDJB-MJHT',
        'verification_uri' => 'https://github.com/login/device',
        'expires_in' => 900,
        'interval' => 5,
        ...$overrides,
    ];
}

/**
 * GitHub's 422 when the signing key is already on the account.
 *
 * @return array<string, mixed>
 */
function gitHubKeyInUseJson(): array
{
    return [
        'message' => 'Validation Failed',
        'errors' => [['resource' => 'SshSigningKey', 'code' => 'custom', 'field' => 'key', 'message' => 'key is already in use']],
        'documentation_url' => 'https://docs.github.com/rest/users/ssh-signing-keys#create-a-ssh-signing-key-for-the-authenticated-user',
        'status' => '422',
    ];
}

/**
 * The runs of the first paragraph whose text contains $text, as [text, style]:
 * 'mention' for a pressable run, 'code' for one on a wash, or nothing for prose.
 *
 * @return list<array{0: string, 1?: string}>
 */
function paragraphRuns(TestableComponent $screen, string $text): array
{
    $find = function (array $node) use (&$find, $text): ?array {
        if (($node['type'] ?? null) === 'text' && ($node['children'] ?? []) !== []
            && str_contains(implode('', array_map(fn (array $run): string => $run['props']['text'] ?? '', $node['children'])), $text)) {
            return $node;
        }

        foreach ($node['children'] ?? [] as $child) {
            if (($found = $find($child)) !== null) {
                return $found;
            }
        }

        return null;
    };

    $paragraph = $find($screen->tree());

    expect($paragraph)->not->toBeNull("No paragraph with [{$text}] was rendered.");

    return array_map(fn (array $run): array => match (true) {
        isset($run['on_press']) => [$run['props']['text'], 'mention'],
        isset($run['style']['bg_color']) => [$run['props']['text'], 'code'],
        default => [$run['props']['text']],
    }, $paragraph['children']);
}

/**
 * The first node in a rendered tree, depth first, that matches.
 *
 * @param  array<string, mixed>  $node
 * @param  callable(array<string, mixed>): bool  $matches
 * @return array<string, mixed>|null
 */
function findNode(array $node, callable $matches): ?array
{
    if ($matches($node)) {
        return $node;
    }

    foreach ($node['children'] ?? [] as $child) {
        if (($found = findNode($child, $matches)) !== null) {
            return $found;
        }
    }

    return null;
}

/**
 * Every node in a rendered tree that matches, in order.
 *
 * @param  array<string, mixed>  $node
 * @param  callable(array<string, mixed>): bool  $matches
 * @return list<array<string, mixed>>
 */
function findNodes(array $node, callable $matches): array
{
    $found = $matches($node) ? [$node] : [];

    foreach ($node['children'] ?? [] as $child) {
        $found = [...$found, ...findNodes($child, $matches)];
    }

    return $found;
}

/**
 * The rendered node with the given ref.
 *
 * @return array<string, mixed>
 */
function nodeByRef(TestableComponent $screen, string $ref): array
{
    $node = findNode($screen->tree(), fn (array $node): bool => ($node['ref'] ?? null) === $ref);

    expect($node)->not->toBeNull("Nothing with ref [{$ref}] was rendered.");

    return $node;
}

/**
 * What sits on the rail of a commit row: 'avatar' for the author's ringed avatar,
 * 'dot' for a lane dot carrying on their run.
 */
function railNode(TestableComponent $screen, string $ref): ?string
{
    $row = nodeByRef($screen, $ref);
    $stackOfWidth = fn (float $width): Closure => fn (array $node): bool => ($node['type'] ?? null) === 'stack' && ($node['layout']['width'] ?? null) === $width;

    return match (true) {
        findNode($row, $stackOfWidth(48.0)) !== null => 'avatar',
        findNode($row, $stackOfWidth(18.0)) !== null => 'dot',
        default => null,
    };
}

/**
 * The visible text of every day marker, top to bottom.
 *
 * @return list<string>
 */
function dayMarkers(TestableComponent $screen): array
{
    return array_map(function (array $marker): string {
        $text = findNode($marker, fn (array $node): bool => ($node['type'] ?? null) === 'text');

        return $text['text'] ?? $text['props']['text'] ?? '';
    }, findNodes($screen->tree(), fn (array $node): bool => str_starts_with((string) ($node['ref'] ?? ''), 'day-')));
}
