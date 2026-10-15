<?php

use App\Devnull\Data\BodyBlock;
use App\Devnull\Data\BodyRun;
use App\Devnull\Data\Commit;
use App\Devnull\Data\CommitBody;
use App\Devnull\Data\Mention;

/**
 * @param  list<BodyBlock>  $blocks
 * @return list<array{0: string, 1?: string}>
 */
function runsOf(array $blocks): array
{
    return array_map(fn (BodyRun $run): array => match (true) {
        $run->isMention() => [$run->text, '@'.$run->username],
        $run->isCode => [$run->text, 'code'],
        default => [$run->text],
    }, $blocks[0]->runs);
}

it('links mentions by code point, so emoji before them do not shift them', function () {
    // "🚀" is one code point but two UTF-16 units and four bytes.
    $body = '🚀🚀 shipped with @ada and 👩‍💻 @grace-h!';

    $blocks = CommitBody::blocks($body, [['type' => 'text', 'text' => $body]], [
        new Mention('ada', 16, 4),
        new Mention('grace-h', 29, 8),
    ]);

    expect($blocks)->toHaveCount(1)
        ->and(runsOf($blocks))->toBe([
            ['🚀🚀 shipped with '],
            ['@ada', '@ada'],
            [' and 👩‍💻 '],
            ['@grace-h', '@grace-h'],
            ['!'],
        ]);
});

it('places mentions on either side of inline code, whose backticks the segments drop', function () {
    $body = 'Ask @nuno to run `composer test` before @taylor merges';

    $blocks = CommitBody::blocks($body, [
        ['type' => 'text', 'text' => 'Ask @nuno to run '],
        ['type' => 'code', 'code' => 'composer test'],
        ['type' => 'text', 'text' => ' before @taylor merges'],
    ], [new Mention('nuno', 4, 5), new Mention('taylor', 40, 7)]);

    expect(runsOf($blocks))->toBe([
        ['Ask '],
        ['@nuno', '@nuno'],
        [' to run '],
        ['composer test', 'code'],
        [' before '],
        ['@taylor', '@taylor'],
        [' merges'],
    ]);
});

it('splits paragraphs around a code block and shows the code as written', function () {
    $body = "Fixed it @ada:\n```php\n\$a = '<b>';\n  return \$a;\n```\nthanks @grace";

    $blocks = CommitBody::blocks($body, [
        ['type' => 'text', 'text' => 'Fixed it @ada:'],
        ['type' => 'block', 'language' => 'php', 'html' => '<span class="hl-variable">$a</span> = …'],
        ['type' => 'text', 'text' => 'thanks @grace'],
    ], [new Mention('ada', 9, 4), new Mention('grace', 58, 6)]);

    expect($blocks)->toHaveCount(3)
        ->and($blocks[0]->runs[1]->username)->toBe('ada')
        ->and($blocks[1]->isCode())->toBeTrue()
        ->and($blocks[1]->code)->toBe("\$a = '<b>';\n  return \$a;")
        ->and($blocks[1]->language)->toBe('php')
        ->and(array_map(fn (BodyRun $run): string => $run->text, $blocks[2]->runs))->toBe(['thanks ', '@grace'])
        ->and($blocks[2]->runs[1]->username)->toBe('grace');
});

it('does not find prose inside a code block it already passed', function () {
    $body = "```\nsee @ada\n```\nsee @ada";

    $blocks = CommitBody::blocks($body, [
        ['type' => 'block', 'language' => null, 'html' => 'see @ada'],
        ['type' => 'text', 'text' => 'see @ada'],
    ], [new Mention('ada', 21, 4)]);

    expect($blocks[0]->code)->toBe('see @ada')
        ->and($blocks[0]->language)->toBeNull()
        ->and(runsOf([$blocks[1]]))->toBe([['see '], ['@ada', '@ada']]);
});

it('leaves out mentions that do not line up with the body', function () {
    $body = 'Hello @ada';

    $blocks = CommitBody::blocks($body, null, [
        new Mention('ada', 5, 4),
        new Mention('ada', 40, 4),
        new Mention('grace', 6, 4),
    ]);

    expect(runsOf($blocks))->toBe([['Hello @ada']]);
});

it('treats a body without segments as one paragraph, and an empty one as nothing', function () {
    expect(runsOf(CommitBody::blocks('Plain', null)))->toBe([['Plain']])
        ->and(CommitBody::blocks('', null))->toBe([]);
});

it('reads mentions and segments straight from the API', function () {
    $commit = Commit::fromArray([
        'id' => 'c1',
        'body' => '😀 @ada `x` @bob',
        'body_segments' => [['type' => 'text', 'text' => '😀 @ada '], ['type' => 'code', 'code' => 'x'], ['type' => 'text', 'text' => ' @bob']],
        'mentions' => [['username' => 'ada', 'offset' => 2, 'length' => 4], ['username' => 'bob', 'offset' => 11, 'length' => 4], ['username' => 'bad']],
        'user' => ['username' => 'local_dev'],
    ]);

    expect($commit->mentions)->toHaveCount(2)
        ->and(runsOf($commit->content))->toBe([['😀 '], ['@ada', '@ada'], [' '], ['x', 'code'], [' '], ['@bob', '@bob']]);
});
