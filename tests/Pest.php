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
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function userJson(array $overrides = []): array
{
    return [
        'id' => '0199e5c4-0000-7000-8000-000000000001',
        'username' => 'local_dev',
        'name' => 'Local Dev',
        'avatar_url' => 'https://avatars.githubusercontent.com/u/1?v=4',
        'bio' => 'Ships things.',
        'lane' => 3,
        ...$overrides,
    ];
}

/**
 * A commit as the API sends it. The body's segments follow the body unless
 * a test sends its own, as the server always splits the body it stores.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function commitJson(array $overrides = []): array
{
    $commit = [
        'id' => '0199e5c4-1111-7000-8000-00000000c0de',
        'body' => 'Shipped the mobile app.',
        'status' => 'approved',
        'parent_id' => null,
        'replies_count' => 0,
        'stars_count' => 0,
        'starred' => false,
        'is_saved' => false,
        'is_pinned' => false,
        'url' => 'http://devnull.test/@local_dev/commits/0199e5c4-1111-7000-8000-00000000c0de',
        'created_at' => now()->subMinutes(5)->toIso8601String(),
        'deleted_at' => null,
        'mentions' => [],
        'link_preview' => null,
        'images' => [],
        'user' => userJson(),
        ...$overrides,
    ];

    if (! array_key_exists('body_segments', $overrides)) {
        $commit['body_segments'] = $commit['body'] === '' ? [] : [['type' => 'text', 'text' => $commit['body']]];
    }

    return $commit;
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
 * @param  list<array<string, mixed>>  $items
 * @return array<string, mixed>
 */
function pageJson(array $items, ?string $nextCursor = null): array
{
    return [
        'data' => $items,
        'meta' => ['next_cursor' => $nextCursor],
        'links' => ['next' => $nextCursor === null ? null : 'http://devnull.test/api/v1/x?cursor='.$nextCursor],
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
