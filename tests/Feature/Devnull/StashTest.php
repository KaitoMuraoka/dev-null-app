<?php

use App\Devnull\Account;
use App\Devnull\Stash;

it('keeps each draft by its name, per user', function () {
    $stash = app(Stash::class);

    $stash->put('u1', 'commit', 'Half a thought');
    $stash->put('u1', 'reply-c2', 'A reply in progress');
    $stash->put('u2', 'commit', 'Someone else on this phone');

    expect($stash->get('u1', 'commit'))->toBe('Half a thought')
        ->and($stash->get('u1', 'reply-c2'))->toBe('A reply in progress')
        ->and($stash->get('u1', 'quote-c2'))->toBe('')
        ->and($stash->get('u2', 'commit'))->toBe('Someone else on this phone')
        ->and($stash->names('u1'))->toBe(['commit', 'reply-c2']);
});

it('forgets a draft once it is emptied', function () {
    $stash = app(Stash::class);

    $stash->put('u1', 'commit', 'Half a thought');
    $stash->put('u1', 'commit', '');

    expect($stash->get('u1', 'commit'))->toBe('')
        ->and($stash->names('u1'))->toBe([]);
});

it('keeps nothing when nobody is signed in', function () {
    $stash = app(Stash::class);

    $stash->put(null, 'commit', 'Half a thought');

    expect($stash->get(null, 'commit'))->toBe('')
        ->and($stash->names(null))->toBe([]);
});

it('lets the draft written longest ago go once there are too many', function () {
    $stash = app(Stash::class);

    foreach (range(1, Stash::MaxDrafts) as $number) {
        $stash->put('u1', "reply-c{$number}", "Reply {$number}");
    }

    // Writing a draft again makes it the newest.
    $stash->put('u1', 'reply-c1', 'Reply 1, again');
    $stash->put('u1', 'commit', 'One too many');

    expect($stash->names('u1'))->toHaveCount(Stash::MaxDrafts)
        ->and($stash->get('u1', 'reply-c1'))->toBe('Reply 1, again')
        ->and($stash->get('u1', 'reply-c2'))->toBe('')
        ->and($stash->get('u1', 'commit'))->toBe('One too many');
});

it("forgets the signed-in user's drafts when the phone signs out", function () {
    signIn();
    $me = app(Account::class)->user()?->id;
    $stash = app(Stash::class);

    $stash->put($me, 'commit', 'Half a thought');
    $stash->put($me, 'quote-c1', 'This, all of it.');
    $stash->put('u2', 'commit', 'Someone else on this phone');

    app(Account::class)->forget();

    expect($stash->names($me))->toBe([])
        ->and($stash->get('u2', 'commit'))->toBe('Someone else on this phone');
});
