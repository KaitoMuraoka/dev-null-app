<?php

use Native\Mobile\Testing\Native;

beforeEach(function () {
    config(['devnull.url' => 'http://devnull.test']);
    signIn();
});

it('lists follows and replies', function () {
    fakeApi(['GET /api/v1/notifications' => [pageJson([
        ['id' => 'n1', 'type' => 'replied', 'read_at' => null, 'created_at' => now()->subHours(3)->toIso8601ZuluString(), 'actor' => userJson(['username' => 'ada', 'name' => 'Ada']), 'commit' => ['id' => 'r1', 'body' => 'Nice work.', 'parent' => ['body' => 'Shipped the app.']]],
        ['id' => 'n2', 'type' => 'followed', 'read_at' => now()->toIso8601ZuluString(), 'created_at' => now()->subDays(2)->toIso8601ZuluString(), 'actor' => userJson(['username' => 'grace', 'name' => 'Grace']), 'commit' => null],
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
        ->assertSee('followed you')
        ->assertSee('2d');
});

it('opens the reply or the follower', function () {
    fakeApi(['GET /api/v1/notifications' => [pageJson([
        ['id' => 'n1', 'type' => 'replied', 'read_at' => null, 'created_at' => now()->toIso8601ZuluString(), 'actor' => userJson(), 'commit' => ['id' => 'r1', 'body' => 'Hi', 'parent' => ['body' => 'Hey']]],
        ['id' => 'n2', 'type' => 'followed', 'read_at' => null, 'created_at' => now()->toIso8601ZuluString(), 'actor' => userJson(['username' => 'grace']), 'commit' => null],
    ])]]);

    Native::visit('/notifications')->tap('notification-n1')->assertNavigatedTo('/commits/r1');
    Native::visit('/notifications')->tap('notification-n2')->assertNavigatedTo('/users/grace');
});

it('speaks git when there is nothing', function () {
    fakeApi(['GET /api/v1/notifications' => [pageJson([])]]);

    Native::visit('/notifications')
        ->assertSee('$ cat /dev/notifications')
        ->assertSee('Nothing here yet. Follows and replies to your commits will show up here.');
});
