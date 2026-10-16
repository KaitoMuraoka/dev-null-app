<?php

use App\Devnull\Account;
use App\Devnull\DeviceKey;
use App\Devnull\Display;
use App\NativeComponents\Layouts\TabsLayout;
use App\NativeComponents\Onboarding;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Native\Mobile\System;
use Native\Mobile\Testing\Native;

beforeEach(fn () => config(['devnull.url' => 'http://devnull.test']));

it('makes a key on first launch and shows the public half', function () {
    $bridge = fakeKeychain();

    $screen = Native::visit('/onboarding');

    $publicKey = app(DeviceKey::class)->current()->openSsh();

    $screen->assertSee('This phone made its own SSH key')
        ->assertSee(Display::maskedKey($publicKey))
        ->assertDontSee($publicKey)
        ->assertSee(app(DeviceKey::class)->current()->fingerprint())
        ->assertSet('publicKey', $publicKey)
        ->assertSee('Connect');

    expect($publicKey)->toStartWith('ssh-ed25519 ')->toEndWith(' devnull-mobile');
    $bridge->assertCalledTimes('SecureStorage.Set', 1);
});

it("wears the Log header's logo as its title, in the system appearance", function () {
    fakeKeychain(testSeed());

    $screen = Native::visit('/onboarding');
    $logo = nodeByRef($screen, 'logo');

    expect($logo['type'])->toBe('image')
        ->and($logo['props'])->toMatchArray(['src' => TabsLayout::LightLogo, 'alt' => 'devnull'])
        ->and($logo['layout'])->toEqual(['width' => 150, 'height' => 40])
        ->and(findNode($screen->tree(), fn (array $node): bool => ($node['type'] ?? null) === 'text' && ($node['props']['text'] ?? null) === 'devnull'))->toBeNull();

    $screen->assertSee('$ ssh-keygen -t ed25519 -C devnull-mobile');

    // Both PNGs hold @3x of the 40pt title, so neither appearance is blown up.
    foreach ([TabsLayout::LightLogo, TabsLayout::DarkLogo] as $png) {
        expect(array_slice(getimagesize(public_path($png)) ?: [], 0, 2))->toBe([450, 120]);
    }

    System::rememberAppearance('dark');

    try {
        expect(nodeByRef(Native::visit('/onboarding'), 'logo')['props']['src'])->toBe(TabsLayout::DarkLogo);
    } finally {
        System::rememberAppearance('light');
    }
});

it('keeps the same key across launches', function () {
    fakeKeychain(testSeed());

    Native::visit('/onboarding')->assertSet('publicKey', testKey()->openSsh());
});

it('adds the key by hand in three numbered steps', function (?string $clientId) {
    fakeKeychain(testSeed());
    config(['devnull.github_client_id' => $clientId]);

    $screen = Native::visit('/onboarding');

    $steps = findNodes($screen->tree(), fn (array $node): bool => preg_match('/^step-\d$/', (string) ($node['ref'] ?? '')) === 1);
    $texts = fn (array $step): array => array_map(
        fn (array $node): string => $node['props']['text'],
        findNodes($step, fn (array $node): bool => ($node['type'] ?? null) === 'text' && isset($node['props']['text'])),
    );
    $refs = fn (array $step): array => array_column(findNodes($step, fn (array $node): bool => isset($node['ref'])), 'ref');

    expect(array_column($steps, 'ref'))->toBe(['step-1', 'step-2', 'step-3'])
        ->and(array_slice($texts($steps[0]), 0, 2))->toBe(['1', 'Copy the key'])
        ->and(array_slice($texts($steps[1]), 0, 2))->toBe(['2', 'Add a signing key on GitHub'])
        ->and(array_slice($texts($steps[2]), 0, 2))->toBe(['3', 'Enter your GitHub username'])
        ->and($refs($steps[0]))->toBe(['step-1', 'public-key', 'copy-key', 'share-key'])
        ->and($refs($steps[1]))->toBe(['step-2', 'github-instructions', 'open-github'])
        ->and($refs($steps[2]))->toBe(['step-3', 'username', 'connect']);

    // GitHub's own labels stand out in the instruction. A run needs the weight as well as the font to draw semibold on iOS.
    $runs = array_map(
        fn (array $run): array => [$run['props']['text'], ($run['props']['font_name'] ?? null) === 'semibold' && ($run['props']['font_weight'] ?? null) === 5],
        nodeByRef($screen, 'github-instructions')['children'],
    );

    expect($runs)->toBe([
        ['On GitHub, choose ', false],
        ['New SSH key', true],
        [', set ', false],
        ['Key type', true],
        [' to ', false],
        ['Signing Key', true],
        [', paste and save.', false],
    ]);

    $screen->assertElement('button', fn (array $node): bool => ($node['ref'] ?? null) === 'copy-key' && $node['props']['label'] === 'Copy key')
        ->assertElement('button', fn (array $node): bool => ($node['ref'] ?? null) === 'share-key' && $node['props']['label'] === 'Share')
        ->assertElement('button', fn (array $node): bool => ($node['ref'] ?? null) === 'open-github' && $node['props']['label'] === 'Open GitHub')
        ->assertElement('button', fn (array $node): bool => ($node['ref'] ?? null) === 'connect' && $node['props']['label'] === 'Connect');
})->with([
    'below Connect GitHub' => ['Ov23liDevnullTest'],
    'on their own without a GitHub client id' => [null],
]);

it('copies the whole key, not the masked one on screen, and says Copied for a moment', function () {
    $this->freezeTime();
    $bridge = fakeKeychain(testSeed())->withClipboard();
    $publicKey = testKey()->openSsh();

    $label = fn ($screen): string => nodeByRef($screen, 'copy-key')['props']['label'];

    $screen = Native::visit('/onboarding')
        ->assertSee(Display::maskedKey($publicKey))
        ->assertDontSee($publicKey)
        ->tap('copy-key')
        ->assertSet('keyCopied', true)
        ->assertSee(Display::maskedKey($publicKey))
        ->assertDontSee($publicKey)
        ->assertDontSee('fatal:');

    $bridge->assertCopied($publicKey)->assertCalledTimes('Clipboard.WriteText', 1);
    expect($label($screen))->toBe('Copied');

    $this->travel(Onboarding::CopiedSeconds - 1)->seconds();
    expect($label($screen->firePoll('tick')))->toBe('Copied');

    $this->travel(1)->seconds();
    expect($label($screen->firePoll('tick')))->toBe('Copy key');
});

it('says so when the key could not be copied', function () {
    $bridge = fakeKeychain(testSeed())->respondTo('Clipboard.WriteText', ['success' => false]);

    Native::visit('/onboarding')
        ->tap('copy-key')
        ->assertSet('keyCopied', false)
        ->assertSee('fatal: could not copy the key. Share it instead.')
        ->assertElement('button', fn (array $node): bool => ($node['ref'] ?? null) === 'copy-key' && $node['props']['label'] === 'Copy key');

    $bridge->assertCalledTimes('Clipboard.WriteText', 1);
});

it('copies nothing without a key', function () {
    $bridge = Native::fakeBridge()->withClipboard()->respondTo('SecureStorage.Get', ['status' => 'unavailable', 'message' => 'Device is locked.']);

    Native::visit('/onboarding')->call('copyKey')->assertSet('keyCopied', false);

    $bridge->assertNothingCopied();
});

it('shares the public key and opens GitHub in the in-app browser', function () {
    fakeKeychain(testSeed());

    Native::visit('/onboarding')
        ->tap('share-key')
        ->assertNativeCalled('Share.File', fn (array $params): bool => $params['message'] === testKey()->openSsh() && $params['filePath'] === '')
        ->tap('open-github')
        ->assertNativeCalled('Browser.OpenInApp', fn (array $params): bool => $params['url'] === 'https://github.com/settings/ssh/new');
});

it('connects, remembers the user and goes to the log', function () {
    fakeKeychain(testSeed())->respondTo('Device.GetInfo', ['info' => json_encode(['model' => 'iPhone'])]);
    Http::fake(['devnull.test/api/v1/key-syncs' => Http::response(['data' => userJson()])]);

    Native::visit('/onboarding')
        ->input('username', '@local_dev ')
        ->tap('connect')
        ->assertReplacedWith('/log');

    expect(app(Account::class)->user()?->username)->toBe('local_dev');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'http://devnull.test/api/v1/key-syncs'
        && $request->body() === '{"username":"local_dev","device_name":"devnull on iPhone"}'
        && $request->hasHeader('X-SSH-Signature'));
});

it('shows the server message as is when the key is not found or the user is', function (int $status, string $message) {
    fakeKeychain(testSeed());
    Http::fake(['devnull.test/*' => Http::response(['message' => $message], $status)]);

    Native::visit('/onboarding')
        ->input('username', 'octocat')
        ->tap('connect')
        ->assertNoNavigation()
        ->assertSee($message);

    expect(app(Account::class)->user())->toBeNull();

    // Connecting by hand never retries on its own.
    Http::assertSentCount(1);
})->with([
    'key not on GitHub yet' => [403, "This SSH key (SHA256:abc) isn't on @octocat's GitHub account yet. Add it on GitHub under Settings → SSH and GPG keys → New SSH key (the key type can be \"Signing Key\"), then try again."],
    'never signed in to devnull' => [404, "@octocat hasn't signed in to devnull yet. Sign in once on http://devnull.test, then try again."],
]);

it('passes on a 503 message too', function () {
    fakeKeychain(testSeed());
    Http::fake(['devnull.test/*' => Http::response(['message' => 'GitHub is not answering right now. Try again in a minute.'], 503)]);

    Native::visit('/onboarding')
        ->input('username', 'octocat')
        ->tap('connect')
        ->assertSee('GitHub is not answering right now. Try again in a minute.');
});

it('does nothing without a username', function () {
    fakeKeychain(testSeed());
    Http::fake();

    Native::visit('/onboarding')->tap('connect')->assertNoNavigation();

    Http::assertNothingSent();
});

it('explains why it came back here after a 401', function () {
    fakeKeychain(testSeed());

    Native::test(Onboarding::class, data: ['message' => 'This key is no longer on your GitHub account.'])
        ->assertSee('This key is no longer on your GitHub account.');
});

it('says so when the Keychain is locked', function () {
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['status' => 'unavailable', 'message' => 'Device is locked.']);

    Native::visit('/onboarding')
        ->assertSee('fatal: could not read the device key from the Keychain. Device is locked.')
        ->assertSet('publicKey', '')
        ->assertNativeNotCalled('SecureStorage.Set');
});
