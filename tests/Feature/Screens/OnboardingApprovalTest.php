<?php

use App\Devnull\Account;
use App\Devnull\GitHubStep;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;
use Native\Mobile\Testing\TestableComponent;

beforeEach(function () {
    config(['devnull.url' => 'http://devnull.test', 'devnull.github_client_id' => null]);
    $this->freezeTime();
    fakeKeychain(testSeed())->respondTo('Device.GetInfo', ['info' => json_encode(['model' => 'iPhone'])]);
});

/**
 * The 202 key-syncs answers with while the phone isn't approved yet.
 *
 * @return array<string, string>
 */
function pairingJson(string $code = 'ABCD-2345', int $minutes = 15): array
{
    return [
        'message' => "Enter {$code} on http://devnull.test/settings/devices to approve this device.",
        'pairing_code' => $code,
        'approval_url' => 'http://devnull.test/settings/devices',
        'expires_at' => now()->addMinutes($minutes)->startOfSecond()->toIso8601ZuluString(),
    ];
}

/**
 * Fakes key-syncs, answering in turn and repeating the last answer. An answer
 * is [body, status, headers], or a closure that builds one when it's asked.
 *
 * @param  list<array{0: array<string, mixed>, 1?: int, 2?: array<string, string>}|Closure>  $answers
 */
function fakeKeySyncs(array $answers): void
{
    $calls = 0;

    Http::fake(function (Request $request) use ($answers, &$calls) {
        if ($request->url() !== 'http://devnull.test/api/v1/key-syncs') {
            return Http::response(['message' => "No fake for {$request->url()}."], 404);
        }

        $answer = $answers[min($calls++, count($answers) - 1)];
        $answer = $answer instanceof Closure ? $answer() : $answer;

        return Http::response($answer[0], $answer[1] ?? 200, $answer[2] ?? []);
    });
}

function keySyncCount(): int
{
    return Http::recorded(fn (Request $request): bool => $request->url() === 'http://devnull.test/api/v1/key-syncs')->count();
}

/**
 * Connects by hand as local_dev.
 */
function connectByHand(): TestableComponent
{
    return Native::visit('/onboarding')->input('username', 'local_dev')->tap('connect');
}

it('shows the code devnull hands out when connecting by hand', function () {
    fakeKeySyncs([[pairingJson(), 202]]);

    connectByHand()
        ->assertNoNavigation()
        ->assertSet('step', GitHubStep::Approving)
        ->assertSee('Approve this phone')
        ->assertSee("A key on GitHub alone isn't enough to act as you, so a key leaked from somewhere else can't.")
        ->assertElement('text', fn (array $node): bool => ($node['props']['text'] ?? null) === 'ABCD-2345'
            && $node['props']['font_name'] === 'mono'
            && $node['props']['font_size'] >= 36)
        ->assertSee('Enter ABCD-2345 on http://devnull.test/settings/devices to approve this device.')
        ->assertSee('It runs out in 15 minutes and a new one takes its place.')
        ->assertSee('Waiting for approval…')
        ->assertDontSee('Add the key yourself')
        ->assertElement('button', fn (array $node): bool => ($node['ref'] ?? null) === 'open-approval-page' && $node['props']['variant'] === 'primary')
        ->tap('open-approval-page')
        ->assertNativeCalled('Browser.OpenInApp', fn (array $params): bool => $params['url'] === 'http://devnull.test/settings/devices')
        ->tap('share-pairing-code')
        ->assertNativeCalled('Share.File', fn (array $params): bool => $params['message'] === 'Enter ABCD-2345 on http://devnull.test/settings/devices to approve this device.');

    expect(app(Account::class)->user())->toBeNull();

    Http::assertSent(fn (Request $request): bool => $request->body() === '{"username":"local_dev","device_name":"devnull on iPhone"}'
        && $request->hasHeader('X-SSH-Signature'));
});

it('asks whether the phone was approved every 6 seconds, never faster', function () {
    fakeKeySyncs([[pairingJson(), 202]]);

    $screen = connectByHand();

    foreach (range(1, 5) as $second) {
        $this->travel(1)->seconds();
        $screen->firePoll('tick');
    }

    expect(keySyncCount())->toBe(1);

    $this->travel(1)->seconds();
    $screen->firePoll('tick')->assertSet('step', GitHubStep::Approving)->assertSee('ABCD-2345');

    expect(keySyncCount())->toBe(2);

    $this->travel(5)->seconds();
    $screen->firePoll('tick');
    expect(keySyncCount())->toBe(2);

    $this->travel(1)->seconds();
    $screen->firePoll('tick');
    expect(keySyncCount())->toBe(3);

    // A minute of ticking every second stays inside devnull's 10 a minute.
    foreach (range(1, 60) as $second) {
        $this->travel(1)->seconds();
        $screen->firePoll('tick');
    }

    expect(keySyncCount())->toBe(13);

    Http::assertSent(fn (Request $request): bool => ! str_contains($request->body(), 'github_token'));
});

it('signs in once the phone is approved on the web', function () {
    fakeKeySyncs([[pairingJson(), 202], [pairingJson(), 202], [['data' => userWithCountsJson()]]]);

    $screen = connectByHand();

    $this->travel(6)->seconds();
    $screen->firePoll('tick')->assertNoNavigation()->assertSee('ABCD-2345');

    $this->travel(6)->seconds();
    $screen->firePoll('tick')->assertReplacedWith('/log');

    expect(app(Account::class)->user()?->username)->toBe('local_dev')
        ->and(keySyncCount())->toBe(3);
});

it('shows the new code once the old one runs out', function () {
    fakeKeySyncs([
        [pairingJson('ABCD-2345'), 202],
        fn (): array => [pairingJson('WXYZ-6789'), 202],
    ]);

    $screen = connectByHand()->assertSee('ABCD-2345');

    $this->travel(15)->minutes();

    $screen->firePoll('tick')
        ->assertSet('step', GitHubStep::Approving)
        ->assertSee('WXYZ-6789')
        ->assertDontSee('ABCD-2345')
        ->assertSee('It runs out in 15 minutes')
        ->tap('share-pairing-code')
        ->assertNativeCalled('Share.File', fn (array $params): bool => str_contains($params['message'], 'WXYZ-6789'));
});

it('counts down the minutes left on a code', function () {
    fakeKeySyncs([[pairingJson(minutes: 15), 202]]);

    $screen = connectByHand();

    $this->travel(10)->minutes();

    $screen->firePoll('tick')->assertSee('It runs out in 5 minutes');
});

it('waits as long as a 429 asks before asking again', function () {
    $tooMany = ['message' => '@local_dev has asked for too many pairing codes. Try again later, or sign in with GitHub in the app.'];
    fakeKeySyncs([[pairingJson(), 202], [$tooMany, 429, ['Retry-After' => '120']], [pairingJson(), 202]]);

    $screen = connectByHand();

    $this->travel(6)->seconds();
    $screen->firePoll('tick')
        ->assertSet('step', GitHubStep::Approving)
        ->assertSee($tooMany['message'].' Asking again in 2 minutes.')
        ->assertSee('ABCD-2345');

    expect(keySyncCount())->toBe(2);

    $this->travel(119)->seconds();
    $screen->firePoll('tick');
    expect(keySyncCount())->toBe(2);

    $this->travel(1)->seconds();
    $screen->firePoll('tick')->assertDontSee('Asking again in');
    expect(keySyncCount())->toBe(3);
});

it('waits a minute after a 429 that does not say how long', function () {
    fakeKeySyncs([[pairingJson(), 202], [['message' => 'Too Many Attempts.'], 429], [pairingJson(), 202]]);

    $screen = connectByHand();

    $this->travel(6)->seconds();
    $screen->firePoll('tick')->assertSee('Too Many Attempts. Asking again in 1 minute.');

    $this->travel(59)->seconds();
    $screen->firePoll('tick');
    expect(keySyncCount())->toBe(2);

    $this->travel(1)->seconds();
    $screen->firePoll('tick');
    expect(keySyncCount())->toBe(3);
});

it('stops showing a code that ran out while devnull asks to wait', function () {
    fakeKeySyncs([[pairingJson(minutes: 1), 202], [['message' => 'Too Many Attempts.'], 429, ['Retry-After' => '30']]]);

    $screen = connectByHand();

    $this->travel(1)->minutes();

    $screen->firePoll('tick')
        ->assertDontSee('ABCD-2345')
        ->assertSee('Asking devnull for a code…')
        ->assertSee('Too Many Attempts. Asking again in 30 seconds.');
});

it('keeps asking when devnull cannot be reached for a moment', function () {
    fakeKeySyncs([
        [pairingJson(), 202],
        fn () => throw new ConnectionException('Could not resolve host'),
        [['data' => userWithCountsJson()]],
    ]);

    $screen = connectByHand();

    $this->travel(6)->seconds();
    $screen->firePoll('tick')
        ->assertSet('step', GitHubStep::Approving)
        ->assertSee('ABCD-2345')
        ->assertSee("fatal: unable to access 'http://devnull.test': could not connect to the server");

    $this->travel(6)->seconds();
    $screen->firePoll('tick')->assertReplacedWith('/log');
});

it('goes back to the start when devnull answers something waiting cannot fix', function () {
    $message = "This SSH key (SHA256:abc) isn't on @local_dev's GitHub account yet. Add it on GitHub under Settings → SSH and GPG keys → New SSH key (the key type can be \"Signing Key\"), then try again.";
    fakeKeySyncs([[pairingJson(), 202], [['message' => $message], 403]]);

    $screen = connectByHand();

    $this->travel(6)->seconds();
    $screen->firePoll('tick')
        ->assertSet('step', GitHubStep::Start)
        ->assertSee($message)
        ->assertDontSee('ABCD-2345')
        ->assertSet('username', 'local_dev');

    $this->travel(6)->seconds();
    $screen->firePoll('tick');

    expect(keySyncCount())->toBe(2);
});

it('goes back to the start on Cancel and stops asking', function () {
    fakeKeySyncs([[pairingJson(), 202]]);

    $screen = connectByHand()
        ->tap('cancel-approval')
        ->assertSet('step', GitHubStep::Start)
        ->assertDontSee('ABCD-2345')
        ->assertSee('Connect')
        ->assertSet('username', 'local_dev');

    $this->travel(6)->seconds();
    $screen->firePoll('tick');

    expect(keySyncCount())->toBe(1);
});

it('explains the approval step once', function () {
    fakeKeySyncs([[pairingJson(), 202]]);

    $tree = json_encode(connectByHand()->tree());

    expect(substr_count($tree, "isn't enough to act as you"))->toBe(1);
});
