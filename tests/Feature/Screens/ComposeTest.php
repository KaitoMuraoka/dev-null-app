<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

beforeEach(function () {
    config(['devnull.url' => 'http://devnull.test']);
    signIn();
});

function budget(): Closure
{
    return fn (string $text, string $color): Closure => fn (array $node): bool => ($node['ref'] ?? null) === 'budget'
        && ($node['text'] ?? $node['props']['text'] ?? null) === $text
        && ($node['props']['color'] ?? null) === config("native-ui.theme.light.{$color}");
}

it('opens in stack chrome with a 280 character budget', function () {
    Native::visit('/compose')
        ->assertNavTitle('New commit')
        ->assertMissingElement('native_root_tabs')
        ->assertSee('What did you ship?')
        ->assertElement('text', budget()('280', 'ink-muted'))
        ->assertElement('button', fn (array $node): bool => ($node['ref'] ?? null) === 'commit' && ($node['props']['disabled'] ?? false));
});

it('counts down in mono and turns red at zero', function () {
    Native::visit('/compose')
        ->input('body', 'Shipped it')
        ->assertElement('text', budget()('270', 'ink-muted'))
        ->assertElement('text', fn (array $node): bool => ($node['ref'] ?? null) === 'budget' && ($node['props']['font_name'] ?? $node['props']['font'] ?? null) !== null)
        ->input('body', str_repeat('x', 280))
        ->assertElement('text', budget()('0', 'destructive'));
});

it('fills the staged node once the commit is long enough', function () {
    $screen = Native::visit('/compose')->input('body', 'hi');

    expect($screen->instance()->canCommit())->toBeFalse();

    $screen->input('body', 'hi!');

    expect($screen->instance()->canCommit())->toBeTrue();
});

it('commits, prints what git would and goes back to the log', function () {
    fakeApi(['POST /api/v1/commits' => [['data' => commitJson(['id' => '0199e5c4-1111-7000-8000-00000000c0de', 'body' => 'Shipped the mobile app.'])], 201]]);

    Native::visit('/compose')
        ->input('body', '  Shipped the mobile app.  ')
        ->tap('commit')
        ->assertNativeCalled('Dialog.Toast', fn (array $params): bool => $params['message'] === '[log 000c0de] Shipped the mobile app.')
        ->assertWentBack();

    Http::assertSent(fn (Request $request): bool => $request->body() === '{"body":"Shipped the mobile app."}');
});

it('says so when the commit is pending', function () {
    fakeApi(['POST /api/v1/commits' => [['data' => commitJson(['id' => 'abc1234', 'status' => 'pending'])], 201]]);

    Native::visit('/compose')
        ->input('body', 'Needs a look first')
        ->tap('commit')
        ->assertNativeCalled('Dialog.Toast', fn (array $params): bool => $params['message'] === "[log abc1234] pending: only you can see it until it's approved.")
        ->assertWentBack();
});

it('keeps the draft and shows the reason when the server says no', function () {
    fakeApi(['POST /api/v1/commits' => [['message' => 'Too Many Attempts.'], 429]]);

    Native::visit('/compose')
        ->input('body', 'One more')
        ->tap('commit')
        ->assertSee('Too Many Attempts.')
        ->assertSet('body', 'One more')
        ->assertNoNavigation();
});

it('sends nothing shorter than three characters', function () {
    Http::fake();

    Native::visit('/compose')->input('body', 'ok')->tap('commit')->assertNoNavigation();

    Http::assertNothingSent();
});
