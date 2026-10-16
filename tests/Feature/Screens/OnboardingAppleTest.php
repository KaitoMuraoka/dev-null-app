<?php

use App\Devnull\Account;
use App\Devnull\AppleSignIn;
use App\Devnull\Client;
use App\NativeComponents\Onboarding;
use Codingwithrk\NativephpSocialAuth\Events\AppleSignInCompleted;
use Codingwithrk\NativephpSocialAuth\Events\SignInFailed;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Native\Mobile\Platform;
use Native\Mobile\Testing\FakeBridge;
use Native\Mobile\Testing\Native;
use Native\Mobile\Testing\TestableComponent;

const AppleToken = 'eyJhbGciOiJSUzI1NiJ9.apple-identity-token.signature';

const AppleNotLinkedMessage = 'No devnull account is linked to this Apple ID. Sign in with GitHub, then link Apple in settings.';

beforeEach(function () {
    config(['devnull.url' => 'http://devnull.test']);
    $this->freezeTime();
});

afterEach(fn () => Platform::set(null));

/**
 * An iPhone with its key in the Keychain, whose Apple sheet opens.
 */
function appleBridge(): FakeBridge
{
    return fakeKeychain(testSeed())
        ->respondTo('NativephpSocialAuth.AppleSignIn', ['pending' => true])
        ->respondTo('Device.GetInfo', ['info' => json_encode(['model' => 'iPhone', 'platform' => 'ios'])]);
}

/**
 * The raw nonce of the Apple sign-in under way, as held in memory.
 */
function heldAppleNonce(): ?string
{
    return (fn () => $this->nonce?->getValue())->call(app(AppleSignIn::class));
}

/**
 * The params of the last call that opened Apple's sheet.
 *
 * @return array<string, mixed>
 */
function appleSheetParams(FakeBridge $bridge): array
{
    $calls = $bridge->callsTo('NativephpSocialAuth.AppleSignIn');

    expect($calls)->not->toBeEmpty("Apple's sheet was never opened.");

    return end($calls)['params'];
}

/**
 * Apple answering, as the plugin sends it: the raw nonce echoed back, and no
 * name or email, since the app asks for neither.
 *
 * @param  array<string, mixed>  $overrides
 */
function appleAnswers(TestableComponent $screen, string $nonce, array $overrides = []): TestableComponent
{
    return $screen->emitNative(AppleSignInCompleted::class, [
        'userId' => '001234.abcdef0123456789.0042',
        'identityToken' => AppleToken,
        'authorizationCode' => 'c0de.apple',
        'email' => null,
        'givenName' => null,
        'familyName' => null,
        'fullName' => null,
        'nonce' => $nonce,
        'state' => null,
        'isRealUser' => 'likelyReal',
        ...$overrides,
    ]);
}

it('offers Sign in with Apple under GitHub on iOS, as big as it and in Apple\'s colours', function () {
    appleBridge();

    $screen = Native::visit('/onboarding', platform: 'ios');
    $buttons = findNodes($screen->tree(), fn (array $node): bool => ($node['type'] ?? null) === 'button');

    expect(array_column($buttons, 'ref'))->toBe(['sign-in', 'sign-in-apple'])
        ->and($buttons[1]['props'])->toMatchArray(['label' => 'Sign in with Apple', 'variant' => 'accent', 'leading_icon' => 'apple.logo'])
        ->and($buttons[1]['layout'])->toEqual($buttons[0]['layout'])->toEqual(['width' => 'fill'])
        ->and($buttons[1]['props'])->not->toHaveKey('size')
        ->and($buttons[0]['props'])->not->toHaveKey('size')
        ->and(array_map(strtolower(...), config('native-ui.theme.light')))->toMatchArray(['accent' => '#000000', 'on-accent' => '#ffffff'])
        ->and(array_map(strtolower(...), config('native-ui.theme.dark')))->toMatchArray(['accent' => '#ffffff', 'on-accent' => '#000000']);
});

it("opens Apple's sheet with a fresh raw nonce, held in memory, and asks for no name or email", function () {
    $bridge = appleBridge();
    $screen = Native::visit('/onboarding', platform: 'ios');

    $screen->tap('sign-in-apple')->assertNoNavigation();
    $first = appleSheetParams($bridge);

    expect($first['nonce'])->toBe(heldAppleNonce())->toMatch('/^[0-9a-f]{64}$/')
        ->and($first['scopes'])->toBe([])
        ->and($first['state'])->toBeNull();

    $screen->tap('sign-in-apple');
    $second = appleSheetParams($bridge);

    expect($second['nonce'])->toBe(heldAppleNonce())->not->toBe($first['nonce']);

    $bridge->assertNotCalled('Browser.OpenAuth');
    Http::assertNothingSent();
});

it("signs in with Apple's token and the raw nonce, which approves this phone", function () {
    $bridge = appleBridge();
    fakeApi(['POST /api/v1/devices' => [['data' => userWithCountsJson()]]]);
    $screen = Native::visit('/onboarding', platform: 'ios')->tap('sign-in-apple');
    $nonce = heldAppleNonce();

    appleAnswers($screen, $nonce)->assertReplacedWith('/log');

    expect(app(Account::class)->user()?->username)->toBe('local_dev')
        ->and(heldAppleNonce())->toBeNull();

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'http://devnull.test/api/v1/'.Client::SignInPath
        && $request->body() === json_encode(['apple_identity_token' => AppleToken, 'nonce' => $nonce, 'device_name' => 'devnull on iPhone'])
        && $request->hasHeader('X-SSH-Signature'));

    // Apple's token carries sha256(nonce) as hex, which is what devnull checks against the raw nonce.
    expect(hash('sha256', $nonce))->toMatch('/^[0-9a-f]{64}$/')->not->toBe($nonce);
});

it("shows devnull's message when no account has this Apple ID linked", function () {
    appleBridge();
    fakeApi(['POST /api/v1/devices' => [['message' => AppleNotLinkedMessage, 'code' => Onboarding::AppleNotLinked], 404]]);
    $screen = Native::visit('/onboarding', platform: 'ios')->tap('sign-in-apple');

    appleAnswers($screen, heldAppleNonce())
        ->assertNoNavigation()
        ->assertSee(AppleNotLinkedMessage)
        ->assertSet('notice', AppleNotLinkedMessage)
        ->assertSet('appleError', null)
        ->assertSet('error', null)
        ->assertElement('button', fn (array $node): bool => ($node['ref'] ?? null) === 'sign-in' && $node['props']['label'] === 'Sign in with GitHub');

    expect(app(Account::class)->user())->toBeNull();
});

it("shows devnull's message when it can't verify Apple's token, without turning GitHub into Try again", function () {
    appleBridge();
    fakeApi(['POST /api/v1/devices' => [['message' => 'That Apple sign-in could not be verified.', 'code' => 'invalid_apple_token'], 422]]);
    $screen = Native::visit('/onboarding', platform: 'ios')->tap('sign-in-apple');

    appleAnswers($screen, heldAppleNonce())
        ->assertNoNavigation()
        ->assertSet('appleError', 'That Apple sign-in could not be verified.')
        ->assertSee('That Apple sign-in could not be verified.')
        ->assertElement('button', fn (array $node): bool => ($node['ref'] ?? null) === 'sign-in' && $node['props']['label'] === 'Sign in with GitHub');

    expect(app(Account::class)->user())->toBeNull();
});

it("returns quietly when Apple's sheet is closed", function () {
    appleBridge();
    fakeApi([]);
    $screen = Native::visit('/onboarding', platform: 'ios')->tap('sign-in-apple');

    $screen->emitNative(SignInFailed::class, ['provider' => 'apple', 'error' => 'Sign in with Apple was cancelled.', 'errorCode' => AppleSignIn::Cancelled])
        ->assertNoNavigation()
        ->assertSet('appleError', null)
        ->assertSet('error', null)
        ->assertSet('notice', null)
        ->assertDontSee('cancelled');

    expect(heldAppleNonce())->toBeNull();
    Http::assertNothingSent();
});

it("says so when Apple's sign-in fails another way, and ignores Google's failures", function () {
    appleBridge();
    fakeApi([]);
    $screen = Native::visit('/onboarding', platform: 'ios')->tap('sign-in-apple');

    $screen->emitNative(SignInFailed::class, ['provider' => 'google', 'error' => 'Nope.', 'errorCode' => 'SIGN_IN_FAILED'])
        ->assertSet('appleError', null);

    expect(heldAppleNonce())->not->toBeNull();

    $screen->emitNative(SignInFailed::class, ['provider' => 'apple', 'error' => 'The operation couldn’t be completed. (ASAuthorizationError.unknown)', 'errorCode' => 'SIGN_IN_FAILED'])
        ->assertSet('appleError', 'fatal: Sign in with Apple failed. The operation couldn’t be completed. (ASAuthorizationError.unknown)');

    expect(heldAppleNonce())->toBeNull();
    Http::assertNothingSent();
});

it('rejects an answer whose nonce is not the one it sent, and sends nothing', function (?string $nonce) {
    appleBridge();
    fakeApi([]);
    $screen = Native::visit('/onboarding', platform: 'ios')->tap('sign-in-apple');

    appleAnswers($screen, '', ['nonce' => $nonce])
        ->assertNoNavigation()
        ->assertSet('appleError', AppleSignIn::NotStartedHere);

    Http::assertNothingSent();
    expect(heldAppleNonce())->toBeNull();
})->with([
    'another nonce' => [str_repeat('ab', 32)],
    'no nonce' => [null],
]);

it('rejects an answer it never asked for', function () {
    appleBridge();
    fakeApi([]);

    appleAnswers(Native::visit('/onboarding', platform: 'ios'), str_repeat('ab', 32))->assertSet('appleError', AppleSignIn::NotStartedHere);

    Http::assertNothingSent();
});

it('spends an attempt on its first answer, so a replayed one is rejected', function () {
    appleBridge();
    fakeApi(['POST /api/v1/devices' => [['message' => 'That Apple sign-in could not be verified.', 'code' => 'invalid_apple_token'], 422]]);
    $screen = Native::visit('/onboarding', platform: 'ios')->tap('sign-in-apple');
    $nonce = heldAppleNonce();

    appleAnswers($screen, $nonce);
    appleAnswers($screen, $nonce)->assertSet('appleError', AppleSignIn::NotStartedHere);

    Http::assertSentCount(1);
});

it('says so when Apple sends no identity token', function () {
    appleBridge();
    fakeApi([]);
    $screen = Native::visit('/onboarding', platform: 'ios')->tap('sign-in-apple');

    appleAnswers($screen, heldAppleNonce(), ['identityToken' => null])->assertSet('appleError', AppleSignIn::NoToken);

    Http::assertNothingSent();
});

it("says so when Apple's sheet does not open, and forgets the nonce", function () {
    fakeKeychain(testSeed())->respondTo('NativephpSocialAuth.AppleSignIn', null);

    Native::visit('/onboarding', platform: 'ios')
        ->tap('sign-in-apple')
        ->assertSee(AppleSignIn::CouldNotOpen);

    expect(heldAppleNonce())->toBeNull();
});

it('never writes the Apple token or the nonce down', function () {
    Storage::fake('local');
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
        $logged[] = $event->message.json_encode($event->context);
    });
    $bridge = appleBridge();
    fakeApi(['POST /api/v1/devices' => [['data' => userWithCountsJson()]]]);
    $screen = Native::visit('/onboarding', platform: 'ios')->tap('sign-in-apple');
    $nonce = heldAppleNonce();

    expect(print_r(app(AppleSignIn::class), true))->not->toContain($nonce)
        ->and(fn () => serialize(app(AppleSignIn::class)))->toThrow(Exception::class);

    appleAnswers($screen, $nonce)->assertReplacedWith('/log');

    $cached = (fn () => $this->storage)->call(Cache::store()->getStore());

    expect(json_encode(get_object_vars($screen->instance())))->not->toContain(AppleToken)->not->toContain($nonce)
        ->and(json_encode($screen->tree()))->not->toContain(AppleToken)->not->toContain($nonce)
        ->and(json_encode($cached))->not->toContain(AppleToken)->not->toContain($nonce)
        ->and(Storage::disk('local')->allFiles())->toBe([])
        ->and(implode("\n", $logged))->not->toContain(AppleToken)->not->toContain($nonce)
        ->and(heldAppleNonce())->toBeNull();

    foreach ($bridge->callsTo('SecureStorage.Set') as $call) {
        expect(json_encode($call['params']))->not->toContain(AppleToken)->not->toContain($nonce);
    }
});

it('waits for the device key, which the sign-in request is signed with', function () {
    $bridge = Native::fakeBridge()
        ->respondTo('SecureStorage.Get', ['status' => 'unavailable', 'message' => 'Device is locked.'])
        ->respondTo('NativephpSocialAuth.AppleSignIn', ['pending' => true]);

    Native::visit('/onboarding', platform: 'ios')
        ->assertElement('button', fn (array $node): bool => ($node['ref'] ?? null) === 'sign-in-apple' && ($node['props']['disabled'] ?? false) === true)
        ->call('signInWithApple');

    $bridge->assertNotCalled('NativephpSocialAuth.AppleSignIn');
});

it('has no Sign in with Apple on Android', function () {
    $bridge = appleBridge();

    $screen = Native::visit('/onboarding', platform: 'android')
        ->assertDontSee('Sign in with Apple')
        ->call('signInWithApple');

    expect(findNodes($screen->tree(), fn (array $node): bool => ($node['type'] ?? null) === 'button'))->toHaveCount(1);
    $bridge->assertNotCalled('NativephpSocialAuth.AppleSignIn');
});
