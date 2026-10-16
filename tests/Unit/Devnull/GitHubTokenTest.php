<?php

use App\Devnull\GitHubToken;

const HeldToken = 'gho_not-a-real-token-for-tests';

it('holds the token until it is forgotten', function () {
    $token = new GitHubToken;

    expect($token->get())->toBeNull();

    $token->hold(HeldToken);
    expect($token->get())->toBe(HeldToken);

    $token->forget();
    expect($token->get())->toBeNull();
});

it('never shows the token in a dump', function () {
    $token = new GitHubToken;
    $token->hold(HeldToken);

    ob_start();
    var_dump($token);
    $dumped = (string) ob_get_clean();

    expect(var_export($token, true))->not->toContain(HeldToken)
        ->and(print_r($token, true))->not->toContain(HeldToken)
        ->and($dumped)->not->toContain(HeldToken);
});

it('cannot be written to disk', function () {
    $token = new GitHubToken;
    $token->hold(HeldToken);

    expect(fn () => serialize($token))->toThrow(Exception::class, "Serialization of 'SensitiveParameterValue' is not allowed");
});
