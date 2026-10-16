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
