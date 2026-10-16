<?php

use App\Devnull\Client;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;
use Native\Mobile\Testing\TestableComponent;

beforeEach(function () {
    config(['devnull.url' => 'http://devnull.test']);
    signIn();
});

/**
 * The avatars stacked on a notification row, as the image URLs drawn bottom to top.
 *
 * @return list<string>
 */
function stackedAvatars(TestableComponent $screen, string $id): array
{
    $images = findNodes(nodeByRef($screen, 'actors-'.$id), fn (array $node): bool => ($node['type'] ?? null) === 'image');

    return array_map(fn (array $image): string => $image['props']['src'] ?? '', $images);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function personJson(string $username, string $name, int $lane = 2, array $overrides = []): array
{
    return userJson(['id' => 'u-'.$username, 'username' => $username, 'name' => $name, 'avatar_url' => "https://avatars.test/{$username}", 'lane' => $lane, ...$overrides]);
}

it('reads a group with its people, how many there are and how many are unread', function () {
    Http::fake(['devnull.test/*' => Http::response(pageJson([
        notificationJson('starred', [
            'actor' => personJson('grace', 'Grace'),
            'actors' => [personJson('grace', 'Grace'), personJson('ada', 'Ada'), personJson('linus', 'Linus')],
            'actors_count' => 4,
            'unread_count' => 2,
        ]),
        notificationJson('followed', ['read_at' => now()->toIso8601ZuluString(), 'unread_count' => 0]),
    ], path: 'http://devnull.test/api/v1/notifications'))]);

    [$stars, $follow] = app(Client::class)->notifications()->items;

    expect($stars->isStar())->toBeTrue()
        ->and($stars->subjectId)->toBe('c-starred')
        ->and(array_map(fn ($actor) => $actor->username, $stars->actors))->toBe(['grace', 'ada', 'linus'])
        ->and($stars->actorsCount)->toBe(4)
        ->and($stars->unreadCount)->toBe(2)
        ->and($stars->isUnread)->toBeTrue()
        ->and($stars->others())->toBe(' and 3 others')
        ->and($follow->isUnread)->toBeFalse()
        ->and($follow->others())->toBe('');
});

it('words a reply, a mention and a follow from one person like the web', function () {
    fakeApi(['GET /api/v1/notifications' => [pageJson([
        notificationJson('replied', ['commit' => notificationCommitJson(['id' => 'r1', 'parent_id' => 'c-yours', 'body' => 'Nice work.', 'parent' => notificationCommitJson(['id' => 'c-yours', 'body' => 'Shipped the app.'], withParent: false)])]),
        notificationJson('mentioned', ['actor' => personJson('grace', 'Grace'), 'actors' => [personJson('grace', 'Grace')], 'commit' => notificationCommitJson(['id' => 'm1', 'body' => 'Ask @local_dev about it'])]),
        notificationJson('followed', ['read_at' => now()->toIso8601ZuluString(), 'unread_count' => 0, 'created_at' => now()->subDays(2)->toIso8601ZuluString(), 'actor' => personJson('linus', 'Linus'), 'actors' => [personJson('linus', 'Linus')]]),
    ])]]);

    $screen = Native::visit('/notifications')
        ->assertNavTitle('Notifications')
        ->assertTabActive('Notifications')
        ->assertSee('Nice work.')
        ->assertSee('↳ Shipped the app.')
        ->assertSee('Ask @local_dev about it')
        ->assertSee('2d');

    expect(paragraphRuns($screen, 'replied to your commit'))->toBe([['Ada'], [' replied to your commit'], [' · '], ['3h']])
        ->and(paragraphRuns($screen, 'mentioned you'))->toBe([['Grace'], [' mentioned you'], [' · '], ['3h']])
        ->and(paragraphRuns($screen, 'followed you'))->toBe([['Linus'], [' followed you'], [' · '], ['2d']])
        ->and(stackedAvatars($screen, 'n-followed'))->toBe(['https://avatars.test/linus']);
});

it('groups replies to your commit into one row, and opens your commit', function () {
    fakeApi(['GET /api/v1/notifications' => [pageJson([notificationJson('replied', [
        'actor' => personJson('grace', 'Grace', 4),
        'actors' => [personJson('grace', 'Grace', 4), personJson('ada', 'Ada')],
        'actors_count' => 2,
        'unread_count' => 2,
        'commit' => notificationCommitJson(['id' => 'r2', 'parent_id' => 'c-yours', 'body' => 'The newest reply', 'parent' => notificationCommitJson(['id' => 'c-yours', 'body' => 'Shipped the app.'], withParent: false)]),
    ])])]]);

    $screen = Native::visit('/notifications')
        ->assertSee('The newest reply')
        ->assertSee('↳ Shipped the app.')
        // The icon wears the newest person's lane, as on the web.
        ->assertElement('icon', fn (array $node): bool => ($node['props']['color'] ?? null) === config('native-ui.theme.light.lane-4'));

    expect(paragraphRuns($screen, 'replied to your commit'))->toBe([['Grace'], [' and 1 other replied to your commit'], [' · '], ['3h']])
        // The newest is drawn last, so it sits on top.
        ->and(stackedAvatars($screen, 'n-replied'))->toBe(['https://avatars.test/ada', 'https://avatars.test/grace']);

    $screen->tap('notification-n-replied')->assertNavigatedTo('/notifications/commits/c-yours');
});

it('opens the reply itself when only one person replied', function () {
    fakeApi(['GET /api/v1/notifications' => [pageJson([notificationJson('replied')])]]);

    Native::visit('/notifications')->tap('notification-n-replied')->assertNavigatedTo('/notifications/commits/c-replied');
});

it('groups stars on your commit, stacking the newest three', function () {
    fakeApi(['GET /api/v1/notifications' => [pageJson([notificationJson('starred', [
        'actor' => personJson('grace', 'Grace', 5),
        'actors' => [personJson('grace', 'Grace', 5), personJson('ada', 'Ada'), personJson('linus', 'Linus')],
        'actors_count' => 4,
        'unread_count' => 4,
        'commit' => notificationCommitJson(['id' => 'c-starred', 'body' => 'Shipped the app.']),
    ])])]]);

    $screen = Native::visit('/notifications')
        ->assertSee('Shipped the app.')
        ->assertDontSee('↳')
        ->assertElement('icon', fn (array $node): bool => ($node['props']['color'] ?? null) === config('native-ui.theme.light.lane-5'));

    expect(paragraphRuns($screen, 'starred your commit'))->toBe([['Grace'], [' and 3 others starred your commit'], [' · '], ['3h']])
        ->and(stackedAvatars($screen, 'n-starred'))->toBe(['https://avatars.test/linus', 'https://avatars.test/ada', 'https://avatars.test/grace'])
        ->and(nodeByRef($screen, 'actors-n-starred')['layout']['width'] ?? null)->toEqual(84);

    $screen->tap('notification-n-starred')->assertNavigatedTo('/notifications/commits/c-starred');
});

it('words moderation updates like the web, with the reason for a rejection', function () {
    fakeApi(['GET /api/v1/notifications' => [pageJson([
        notificationJson('approved'),
        notificationJson('rejected', ['reason' => 'Please keep it about code.']),
    ])]]);

    Native::visit('/notifications')
        ->assertSee('Moderation update')
        ->assertElement('text', fn (array $node): bool => ($node['props']['text'] ?? null) === 'Your commit was approved')
        ->assertElement('text', fn (array $node): bool => ($node['props']['text'] ?? null) === 'Your commit was rejected')
        ->assertSee('About rejected')
        ->assertSee('Reason: ')
        ->assertSee('Please keep it about code.')
        ->assertSee('Only you can see this update and your rejected commit.')
        // devnull names you as the actor of your own moderation update, so no name or avatar is shown,
        // and the icon is muted rather than a lane.
        ->assertDontSee('Local Dev')
        ->assertMissingElement('column', fn (array $node): bool => str_starts_with((string) ($node['ref'] ?? ''), 'actors-'))
        ->assertElement('icon', fn (array $node): bool => ($node['props']['color'] ?? null) === config('native-ui.theme.light.ink-muted'));
});

it('leaves out an empty reason', function () {
    fakeApi(['GET /api/v1/notifications' => [pageJson([notificationJson('rejected', ['reason' => null])])]]);

    Native::visit('/notifications')
        ->assertDontSee('Reason: ')
        ->assertSee('Only you can see this update and your rejected commit.');
});

it('leaves out a parent you are not allowed to see', function () {
    fakeApi(['GET /api/v1/notifications' => [pageJson([
        notificationJson('replied', ['commit' => notificationCommitJson(['id' => 'r1', 'parent_id' => 'c-yours', 'body' => 'Replying to something pending'])]),
    ])]]);

    Native::visit('/notifications')
        ->assertSee('Replying to something pending')
        ->assertDontSee('↳');
});

it('marks a group unread while any of it is unread', function () {
    fakeApi(['GET /api/v1/notifications' => [pageJson([
        // The newest in the group was read, an older one wasn't.
        notificationJson('starred', ['read_at' => now()->toIso8601ZuluString(), 'actors_count' => 2, 'unread_count' => 1]),
        notificationJson('followed', ['read_at' => now()->toIso8601ZuluString(), 'unread_count' => 0]),
    ])]]);

    $screen = Native::visit('/notifications');

    expect(nodeByRef($screen, 'notification-n-starred')['style']['bg_color'] ?? null)->toBe(config('native-ui.theme.light.primary-soft'))
        ->and(nodeByRef($screen, 'notification-n-followed')['style']['bg_color'] ?? null)->toBe(config('native-ui.theme.light.surface'));
});

it('opens what the web opens for each kind', function (string $type, string $destination) {
    fakeApi(['GET /api/v1/notifications' => [pageJson([notificationJson($type)])]]);

    Native::visit('/notifications')->tap('notification-n-'.$type)->assertNavigatedTo($destination);
})->with([
    ['replied', '/notifications/commits/c-replied'],
    ['starred', '/notifications/commits/c-starred'],
    ['mentioned', '/notifications/commits/c-mentioned'],
    ['approved', '/notifications/commits/c-approved'],
    ['rejected', '/notifications/commits/c-rejected'],
    ['followed', '/notifications/users/ada'],
]);

it('speaks git when there is nothing', function () {
    fakeApi(['GET /api/v1/notifications' => [pageJson([])]]);

    Native::visit('/notifications')
        ->assertSee('$ cat /dev/notifications')
        ->assertSee('Nothing here yet. Follows, stars, replies, mentions, and moderation updates for your commits will show up here.');
});
