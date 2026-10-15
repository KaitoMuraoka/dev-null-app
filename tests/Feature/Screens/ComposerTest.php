<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;
use Native\Mobile\Testing\TestableComponent;

beforeEach(function () {
    config(['devnull.url' => 'http://devnull.test']);
    signIn();
});

function composerBudget(): Closure
{
    return fn (string $text, string $color): Closure => fn (array $node): bool => ($node['ref'] ?? null) === 'budget'
        && ($node['text'] ?? $node['props']['text'] ?? null) === $text
        && ($node['props']['color'] ?? null) === config("native-ui.theme.light.{$color}");
}

function commitButton(bool $disabled): Closure
{
    return fn (array $node): bool => ($node['ref'] ?? null) === 'commit' && ($node['props']['disabled'] ?? false) === $disabled;
}

/**
 * The composer's field, as the native side would mount it.
 *
 * @return array<string, mixed>
 */
function composerField(TestableComponent $screen): array
{
    return nodeByRef($screen, 'body');
}

it('is a field at the top of the log, with no budget or Commit until you are in it', function () {
    Http::fake(['devnull.test/*' => Http::response(pageJson([commitJson()]))]);

    Native::visit('/log')
        ->assertElement('bare_text_input', fn (array $node): bool => ($node['ref'] ?? null) === 'body'
            && ($node['props']['placeholder'] ?? null) === 'What did you ship?'
            && ($node['props']['max_length'] ?? null) === 280
            && ($node['props']['multiline'] ?? false))
        ->assertMissingElement('text', fn (array $node): bool => ($node['ref'] ?? null) === 'budget')
        ->assertMissingElement('button', fn (array $node): bool => ($node['ref'] ?? null) === 'commit');
});

it('opens where it is when you tap into it: the 280 budget and a disabled Commit, and no navigation', function () {
    Http::fake(['devnull.test/*' => Http::response(pageJson([commitJson()]))]);

    Native::visit('/log')
        ->pressUp('composer-field')
        ->assertNoNavigation()
        ->assertSet('composing', true)
        ->assertElement('text', composerBudget()('280', 'ink-muted'))
        ->assertElement('text', fn (array $node): bool => ($node['ref'] ?? null) === 'budget' && ($node['props']['font_name'] ?? $node['props']['font'] ?? null) !== null)
        ->assertElement('button', commitButton(disabled: true));
});

it('stays open once it has a draft', function () {
    Http::fake(['devnull.test/*' => Http::response(pageJson([commitJson()]))]);

    Native::visit('/log')
        ->input('body', 'Shipped it')
        ->assertElement('text', composerBudget()('270', 'ink-muted'))
        ->assertElement('button', commitButton(disabled: false));
});

it('counts down in mono and turns red at zero', function () {
    Http::fake(['devnull.test/*' => Http::response(pageJson([commitJson()]))]);

    Native::visit('/log')
        ->pressUp('composer-field')
        ->input('body', 'Shipped it')
        ->assertElement('text', composerBudget()('270', 'ink-muted'))
        ->input('body', str_repeat('x', 280))
        ->assertElement('text', composerBudget()('0', 'destructive'));
});

it('enables Commit once the commit is long enough', function () {
    Http::fake(['devnull.test/*' => Http::response(pageJson([commitJson()]))]);

    Native::visit('/log')
        ->pressUp('composer-field')
        ->input('body', 'hi')
        ->assertElement('button', commitButton(disabled: true))
        ->input('body', '  hi  ')
        ->assertElement('button', commitButton(disabled: true))
        ->input('body', 'hi!')
        ->assertElement('button', commitButton(disabled: false));
});

it('stages your avatar on the rail in a dashed ring, solid once the commit is long enough', function () {
    Http::fake(['devnull.test/*' => Http::response(pageJson([commitJson(['user' => userJson(['id' => 'u2', 'username' => 'ada', 'lane' => 1])])]))]);

    $lane = config('native-ui.theme.light.lane-3');
    $solidRing = fn (array $node): bool => ($node['type'] ?? null) === 'stack' && ($node['layout']['width'] ?? null) === 48.0 && ($node['style']['bg_color'] ?? null) === $lane;
    $stagedRing = fn (array $node): bool => ($node['ref'] ?? null) === 'staged-avatar';

    Native::visit('/log')
        ->assertElement('column', $stagedRing)
        ->assertMissingElement('stack', $solidRing)
        ->input('body', 'hi')
        ->assertElement('column', $stagedRing)
        ->input('body', 'hi!')
        ->assertMissingElement('column', $stagedRing)
        ->assertElement('stack', $solidRing);
});

it('commits without leaving the log, then clears the composer and puts the commit at the top', function () {
    fakeApi([
        'GET /api/v1/log/following' => [pageJson([commitJson(['id' => 'c1', 'body' => 'An older commit', 'user' => userJson(['id' => 'u2', 'username' => 'ada'])])])],
        'POST /api/v1/commits' => [['data' => commitJson(['id' => '0199e5c4-1111-7000-8000-00000000beef', 'body' => 'Shipped the mobile app.'])], 201],
    ]);

    $screen = Native::visit('/log')->pressUp('composer-field')->input('body', '  Shipped the mobile app.  ');
    $draftField = composerField($screen)['id'];

    $screen->tap('commit')
        ->assertNoNavigation()
        ->assertNativeCalled('Dialog.Toast', fn (array $params): bool => $params['message'] === '[log 000beef] Shipped the mobile app.')
        ->assertSet('body', '')
        ->assertSet('composing', false)
        ->assertSee('Shipped the mobile app.')
        ->assertMissingElement('text', fn (array $node): bool => ($node['ref'] ?? null) === 'budget')
        ->assertMissingElement('button', fn (array $node): bool => ($node['ref'] ?? null) === 'commit');

    // A fresh, empty field: the committed one is gone, so the next tap into it opens it again.
    expect(composerField($screen)['id'])->not->toBe($draftField)
        ->and(composerField($screen)['props']['value'] ?? null)->toBe('')
        ->and(array_map(fn ($commit): string => $commit->id, $screen->instance()->commits))->toBe(['0199e5c4-1111-7000-8000-00000000beef', 'c1']);

    $json = json_encode($screen->tree());
    expect(strpos($json, 'commit-0199e5c4-1111-7000-8000-00000000beef'))->toBeLessThan(strpos($json, 'commit-c1'));

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && $request->body() === '{"body":"Shipped the mobile app."}');
});

it('says so when the commit is pending', function () {
    fakeApi([
        'GET /api/v1/log/following' => [pageJson([])],
        'POST /api/v1/commits' => [['data' => commitJson(['id' => 'abc1234', 'body' => 'Needs a look first', 'status' => 'pending'])], 201],
    ]);

    Native::visit('/log')
        ->input('body', 'Needs a look first')
        ->tap('commit')
        ->assertNativeCalled('Dialog.Toast', fn (array $params): bool => $params['message'] === "[log abc1234] pending: only you can see it until it's approved.")
        ->assertNoNavigation()
        ->assertSee('Needs a look first')
        ->assertSee('pending');
});

it('keeps the draft and shows the reason when the server says no', function () {
    fakeApi([
        'GET /api/v1/log/following' => [pageJson([])],
        'POST /api/v1/commits' => [['message' => 'Too Many Attempts.'], 429],
    ]);

    Native::visit('/log')
        ->input('body', 'One more')
        ->tap('commit')
        ->assertSee('Too Many Attempts.')
        ->assertSet('body', 'One more')
        ->assertElement('button', commitButton(disabled: false))
        ->assertNoNavigation();
});

it('sends nothing shorter than three characters', function () {
    fakeApi(['GET /api/v1/log/following' => [pageJson([])]]);

    Native::visit('/log')->input('body', 'ok')->call('commit')->assertSet('body', 'ok');

    Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
});

it('syncs the draft on a short debounce so a burst of typing arrives whole', function () {
    Http::fake(['devnull.test/*' => Http::response(pageJson([]))]);

    // In live mode a burst of keystrokes can overflow the native event queue,
    // and the input never resends the dropped tail. Debounce sends one trailing value.
    Native::visit('/log')->assertElement('bare_text_input', fn (array $node): bool => ($node['ref'] ?? null) === 'body'
        && ($node['props']['sync_mode'] ?? null) === 'debounce'
        && ($node['props']['debounce_ms'] ?? null) === 150);
});

it('closes an empty composer when the log reloads, and keeps one with a draft open', function () {
    Http::fake(['devnull.test/*' => Http::response(pageJson([]))]);

    Native::visit('/log')
        ->pressUp('composer-field')
        ->assertSet('composing', true)
        ->call('load')
        ->assertSet('composing', false)
        ->assertMissingElement('button', fn (array $node): bool => ($node['ref'] ?? null) === 'commit');

    Native::visit('/log')
        ->pressUp('composer-field')
        ->input('body', 'Still thinking')
        ->call('load')
        ->assertSet('composing', true)
        ->assertSet('body', 'Still thinking')
        ->assertElement('button', commitButton(disabled: false));
});
