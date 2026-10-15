<?php

use Native\Mobile\Testing\Native;

beforeEach(function () {
    config(['devnull.url' => 'http://devnull.test']);
    signIn();
});

/**
 * A notification as the API sends it. The commit is a full commit, and its
 * parent is null when you can't see it.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function notificationJson(string $type, array $overrides = []): array
{
    return [
        'id' => 'n-'.$type,
        'type' => $type,
        'reason' => null,
        'read_at' => null,
        'created_at' => now()->subHours(3)->toIso8601ZuluString(),
        'actor' => userJson(['username' => 'ada', 'name' => 'Ada', 'lane' => 2]),
        'commit' => $type === 'followed' ? null : commitJson(['id' => 'c-'.$type, 'body' => 'About '.$type]),
        ...$overrides,
    ];
}

it('words follows, replies and mentions like the web', function () {
    fakeApi(['GET /api/v1/notifications' => [pageJson([
        notificationJson('replied', ['commit' => commitJson(['id' => 'r1', 'body' => 'Nice work.', 'parent' => commitJson(['body' => 'Shipped the app.'])])]),
        notificationJson('mentioned', ['actor' => userJson(['username' => 'grace', 'name' => 'Grace']), 'commit' => commitJson(['id' => 'm1', 'body' => 'Ask @local_dev about it'])]),
        notificationJson('followed', ['read_at' => now()->toIso8601ZuluString(), 'created_at' => now()->subDays(2)->toIso8601ZuluString(), 'actor' => userJson(['username' => 'linus', 'name' => 'Linus'])]),
    ])]]);

    Native::visit('/notifications')
        ->assertNavTitle('Notifications')
        ->assertTabActive('Notifications')
        ->assertSee('Ada')
        ->assertSee('replied to your commit')
        ->assertSee('Nice work.')
        ->assertSee('↳ Shipped the app.')
        ->assertSee('3h')
        ->assertSee('Grace')
        ->assertSee('mentioned you')
        ->assertSee('Ask @local_dev about it')
        ->assertSee('Linus')
        ->assertSee('followed you')
        ->assertSee('2d');
});

it('leaves out a parent you are not allowed to see', function () {
    fakeApi(['GET /api/v1/notifications' => [pageJson([
        notificationJson('replied', ['commit' => commitJson(['id' => 'r1', 'body' => 'Replying to something pending', 'parent' => null])]),
    ])]]);

    Native::visit('/notifications')
        ->assertSee('Replying to something pending')
        ->assertDontSee('↳');
});

it('words moderation updates like the web, with the reason for a rejection', function () {
    fakeApi(['GET /api/v1/notifications' => [pageJson([
        notificationJson('approved', ['actor' => userJson(['username' => 'mod', 'name' => 'A Moderator'])]),
        notificationJson('rejected', ['reason' => 'Please keep it about code.', 'actor' => userJson(['username' => 'mod', 'name' => 'A Moderator'])]),
    ])]]);

    Native::visit('/notifications')
        ->assertSee('Moderation update')
        ->assertElement('text', fn (array $node): bool => ($node['props']['text'] ?? null) === 'Your commit was approved')
        ->assertElement('text', fn (array $node): bool => ($node['props']['text'] ?? null) === 'Your commit was rejected')
        ->assertSee('About rejected')
        ->assertSee('Reason: ')
        ->assertSee('Please keep it about code.')
        ->assertSee('Only you can see this update and your rejected commit.')
        // A moderator stays anonymous, and their icon is muted rather than a lane.
        ->assertDontSee('A Moderator')
        ->assertElement('icon', fn (array $node): bool => ($node['props']['color'] ?? null) === config('native-ui.theme.light.ink-muted'));
});

it('leaves out an empty reason', function () {
    fakeApi(['GET /api/v1/notifications' => [pageJson([notificationJson('rejected', ['reason' => null])])]]);

    Native::visit('/notifications')
        ->assertDontSee('Reason: ')
        ->assertSee('Only you can see this update and your rejected commit.');
});

it('opens the commit for everything but a follow', function (string $type, string $destination) {
    fakeApi(['GET /api/v1/notifications' => [pageJson([notificationJson($type)])]]);

    Native::visit('/notifications')->tap('notification-n-'.$type)->assertNavigatedTo($destination);
})->with([
    ['replied', '/commits/c-replied'],
    ['mentioned', '/commits/c-mentioned'],
    ['approved', '/commits/c-approved'],
    ['rejected', '/commits/c-rejected'],
    ['followed', '/users/ada'],
]);

it('speaks git when there is nothing', function () {
    fakeApi(['GET /api/v1/notifications' => [pageJson([])]]);

    Native::visit('/notifications')
        ->assertSee('$ cat /dev/notifications')
        ->assertSee('Nothing here yet. Follows, replies, mentions, and moderation updates for your commits will show up here.');
});
