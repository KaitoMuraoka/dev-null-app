<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

beforeEach(function () {
    config(['devnull.url' => 'http://devnull.test']);
    signIn();
});

/**
 * @return array<string, mixed>
 */
function profileJson(bool $isFollowing = false, array $user = []): array
{
    return [
        'data' => [...userJson(['id' => 'u2', 'username' => 'ada', 'name' => 'Ada Lovelace', 'bio' => 'Wrote the first program.', ...$user]), 'commits_count' => 1, 'followers_count' => 5, 'following_count' => 2],
        'is_following' => $isFollowing,
        'activity' => [],
    ];
}

it('shows who they are, their counts and their commits', function () {
    fakeApi([
        'GET /api/v1/users/ada' => [profileJson()],
        'GET /api/v1/users/ada/commits' => [pageJson([commitJson(['body' => 'Notes on the engine', 'user' => userJson(['id' => 'u2', 'username' => 'ada'])])])],
    ]);

    Native::visit('/users/ada')
        ->assertNavTitle('@ada')
        ->assertSee('Ada Lovelace')
        ->assertSee('@ada')
        ->assertSee('Wrote the first program.')
        ->assertSee('1')->assertSee('commit')
        ->assertSee('5')->assertSee('followers')
        ->assertSee('2')->assertSee('following')
        ->assertSee('Follow')
        ->assertSee('Notes on the engine')
        ->assertSee("That's @ada's root commit.");
});

it('rings the avatar in the author lane', function () {
    fakeApi([
        'GET /api/v1/users/ada' => [profileJson(user: ['id' => 'local_dev'])],
        'GET /api/v1/users/ada/commits' => [pageJson([])],
    ]);

    Native::visit('/users/ada')
        ->assertElement('stack', fn (array $node): bool => ($node['layout']['width'] ?? null) === 92.0
            && ($node['style']['bg_color'] ?? null) === config('native-ui.theme.light.lane-5'))
        ->assertSee('fatal: no commits yet');
});

it('follows and unfollows', function () {
    fakeApi([
        'GET /api/v1/users/ada' => [profileJson()],
        'GET /api/v1/users/ada/commits' => [pageJson([])],
        'POST /api/v1/users/ada/follow' => [null, 204],
        'DELETE /api/v1/users/ada/follow' => [null, 204],
    ]);

    Native::visit('/users/ada')
        ->tap('follow')
        ->assertSee('Unfollow')
        ->assertSee('6')
        ->tap('follow')
        ->assertSee('Follow')
        ->assertSee('5');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && $request->url() === 'http://devnull.test/api/v1/users/ada/follow');
    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && $request->url() === 'http://devnull.test/api/v1/users/ada/follow');
});

it('puts the follow back when the server refuses', function () {
    fakeApi([
        'GET /api/v1/users/ada' => [profileJson()],
        'GET /api/v1/users/ada/commits' => [pageJson([])],
        'POST /api/v1/users/ada/follow' => [['message' => 'Too Many Attempts.'], 429],
    ]);

    $screen = Native::visit('/users/ada')->tap('follow')->assertSee('Too Many Attempts.');

    expect($screen->instance()->profile->isFollowing)->toBeFalse()
        ->and($screen->instance()->profile->followersCount)->toBe(5);
});

it('has no follow button on your own profile', function () {
    fakeApi([
        'GET /api/v1/users/local_dev' => [profileJson(user: ['username' => 'local_dev'])],
        'GET /api/v1/users/local_dev/commits' => [pageJson([])],
    ]);

    Native::visit('/users/local_dev')
        ->assertMissingElement('button', fn (array $node): bool => ($node['ref'] ?? null) === 'follow')
        ->assertSee('fatal: you do not have any commits yet');
});

it('loads older commits by cursor', function () {
    Http::fake(function (Request $request) {
        return match (true) {
            str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/users/ada') => Http::response(profileJson()),
            str_contains($request->url(), 'cursor=older') => Http::response(pageJson([commitJson(['id' => 'c1', 'body' => 'Older'])])),
            default => Http::response(pageJson([commitJson(['id' => 'c2', 'body' => 'Newer'])], 'older')),
        };
    });

    Native::visit('/users/ada')
        ->assertDontSee('Older')
        ->call('loadMore')
        ->assertSee('Older');
});
