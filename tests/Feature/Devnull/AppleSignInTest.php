<?php

use App\Devnull\AppleSignIn;
use Native\Mobile\Platform;
use Native\Mobile\Testing\Native;

afterEach(fn () => Platform::set(null));

it('gives the plugin a fresh raw nonce for every attempt, and keeps it only in memory', function () {
    $bridge = Native::fakeBridge()->respondTo('NativephpSocialAuth.AppleSignIn', ['pending' => true]);
    $signIn = new AppleSignIn;

    expect($signIn->start())->toBeTrue()
        ->and($signIn->start())->toBeTrue();

    [$first, $second] = array_map(fn (array $call): string => $call['params']['nonce'], $bridge->callsTo('NativephpSocialAuth.AppleSignIn'));

    expect($first)->toMatch('/^[0-9a-f]{64}$/')
        ->and($second)->toMatch('/^[0-9a-f]{64}$/')->not->toBe($first)
        ->and(print_r($signIn, true))->not->toContain($second)
        ->and(var_export((array) $signIn, true))->not->toContain($second)
        ->and(fn () => serialize($signIn))->toThrow(Exception::class)
        ->and($signIn->take($first))->toBeNull('An earlier attempt was answered.');
});

it('takes an answer once, and only for the attempt under way', function () {
    $bridge = Native::fakeBridge()->respondTo('NativephpSocialAuth.AppleSignIn', ['pending' => true]);
    $signIn = new AppleSignIn;

    $signIn->start();
    $nonce = $bridge->callsTo('NativephpSocialAuth.AppleSignIn')[0]['params']['nonce'];

    expect($signIn->take($nonce))->toBe($nonce)
        ->and($signIn->take($nonce))->toBeNull();

    $signIn->start();

    expect($signIn->take(strtoupper($bridge->callsTo('NativephpSocialAuth.AppleSignIn')[1]['params']['nonce'])))->toBeNull()
        ->and($signIn->take($bridge->callsTo('NativephpSocialAuth.AppleSignIn')[1]['params']['nonce']))->toBeNull('A wrong answer spends the attempt.');

    $signIn->start();
    $signIn->forget();

    expect($signIn->take($bridge->callsTo('NativephpSocialAuth.AppleSignIn')[2]['params']['nonce']))->toBeNull();
});

it('passes the nonce raw because the plugin hashes it for Apple', function () {
    $swift = file_get_contents(base_path('vendor/codingwithrk/nativephp-social-auth/resources/ios/Sources/NativephpSocialAuthFunctions.swift'));
    $support = file_get_contents(base_path('vendor/codingwithrk/nativephp-social-auth/resources/ios/Sources/NativephpSocialAuthDelegates.swift'));

    expect($swift)->toContain('request.nonce = SocialAuthSupport.sha256Hex(rawNonce)')
        ->and($support)->toContain('SHA256.hash(data: Data(input.utf8))')
        ->and($support)->toContain('String(format: "%02x", $0)')
        ->and($support)->toContain('"nonce": SocialAuthSupport.payloadValue(rawNonce)');
});

it('is only offered on iOS', function (?string $platform, bool $available) {
    Native::fakeBridge();
    Platform::set($platform);

    expect(AppleSignIn::isAvailable())->toBe($available);
})->with([
    'iOS' => ['ios', true],
    'Android' => ['android', false],
    'off a phone' => [null, false],
]);

it('adds the Sign in with Apple entitlement and builds without Google', function () {
    $manifest = json_decode((string) file_get_contents(base_path('vendor/codingwithrk/nativephp-social-auth/nativephp.json')), true);
    $required = array_keys(array_filter($manifest['secrets'], fn (array $secret): bool => $secret['required'] ?? true));

    expect($manifest['ios']['entitlements'])->toBe(['com.apple.developer.applesignin' => ['Default']])
        ->and($required)->toBe(['GOOGLE_SERVER_CLIENT_ID'])
        ->and(file_get_contents(base_path('.env.example')))->toContain("\nGOOGLE_SERVER_CLIENT_ID=unused\n")
        ->toContain("\nGOOGLE_IOS_REVERSED_CLIENT_ID=\"\${NATIVEPHP_DEEPLINK_SCHEME}\"\n");
});
