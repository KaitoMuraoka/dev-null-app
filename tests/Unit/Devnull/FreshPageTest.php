<?php

use App\Devnull\Data\Page;
use App\Devnull\FreshPage;

/**
 * @return list<object{id: string, v: int}>
 */
function rows(string ...$ids): array
{
    return array_map(fn (string $id): object => (object) ['id' => $id, 'v' => 0], $ids);
}

/**
 * @param  list<object>  $items
 * @return list<string>
 */
function idsOf(array $items): array
{
    return array_map(fn (object $item): string => $item->id, $items);
}

it('shows a fresh page as it is when nothing was showing', function () {
    $fresh = FreshPage::over([], null, new Page(rows('c3', 'c2'), 'next'));

    expect(idsOf($fresh->items))->toBe(['c3', 'c2'])
        ->and($fresh->nextCursor)->toBe('next')
        ->and($fresh->hasNewer())->toBeFalse();
});

it('holds commits newer than anything showing, and changes the rest in place', function () {
    $fresh = new Page([(object) ['id' => 'c4', 'v' => 0], (object) ['id' => 'c3', 'v' => 0], (object) ['id' => 'c2', 'v' => 9], (object) ['id' => 'c1', 'v' => 0]], 'fresh');

    $page = FreshPage::over(rows('c2', 'c1'), null, $fresh);

    expect(idsOf($page->items))->toBe(['c2', 'c1'])
        ->and($page->items[0]->v)->toBe(9)
        ->and(idsOf($page->newer))->toBe(['c4', 'c3'])
        ->and($page->replacesAll)->toBeFalse()
        ->and(idsOf($page->withNewer()->items))->toBe(['c4', 'c3', 'c2', 'c1']);
});

it('keeps the pages loaded further down, and their cursor', function () {
    $page = FreshPage::over(rows('c3', 'c2', 'c1', 'c0'), 'after-c0', new Page(rows('c4', 'c3', 'c2'), 'after-c2'));

    expect(idsOf($page->items))->toBe(['c3', 'c2', 'c1', 'c0'])
        ->and($page->nextCursor)->toBe('after-c0')
        ->and(idsOf($page->withNewer()->items))->toBe(['c4', 'c3', 'c2', 'c1', 'c0'])
        ->and($page->withNewer()->nextCursor)->toBe('after-c0');
});

it("takes the fresh cursor when the fresh page covers everything that's showing", function () {
    $page = FreshPage::over(rows('c2', 'c1'), null, new Page(rows('c3', 'c2', 'c1', 'c0'), 'after-c0'));

    expect(idsOf($page->items))->toBe(['c2', 'c1', 'c0'])
        ->and($page->nextCursor)->toBe('after-c0');
});

it('drops rows devnull no longer sends between the ones it does', function () {
    $page = FreshPage::over(rows('c3', 'c2', 'c1'), null, new Page(rows('c3', 'c1'), null));

    expect(idsOf($page->items))->toBe(['c3', 'c1']);
});

it('keeps a commit you just made when the answer left before you made it', function () {
    $page = FreshPage::over(rows('mine', 'c2', 'c1'), null, new Page(rows('c2', 'c1'), null), madeHere: ['mine']);

    expect(idsOf($page->items))->toBe(['mine', 'c2', 'c1'])
        ->and($page->hasNewer())->toBeFalse();
});

it('drops a commit on top that devnull no longer sends, such as one you deleted', function () {
    $page = FreshPage::over(rows('gone', 'c2', 'c1'), null, new Page(rows('c2', 'c1'), null));

    expect(idsOf($page->items))->toBe(['c2', 'c1']);
});

it('replaces the list when the fresh page has nothing in common with it', function () {
    $page = FreshPage::over(rows('c2', 'c1'), null, new Page(rows('c9', 'c8'), 'after-c8'));

    expect(idsOf($page->items))->toBe(['c2', 'c1'])
        ->and(idsOf($page->newer))->toBe(['c9', 'c8'])
        ->and($page->replacesAll)->toBeTrue()
        ->and(idsOf($page->withNewer()->items))->toBe(['c9', 'c8'])
        ->and($page->withNewer()->nextCursor)->toBe('after-c8');
});
