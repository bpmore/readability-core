<?php

declare(strict_types=1);

use Bpmore\ReadabilityCore\Extraction\BardExtractor;

it('reads a Bard tree as the blocks a reader would read, in order', function () {
    $document = (new BardExtractor)->extract(readFixture('bard.json'));

    expect(outline($document))->toBe([
        ['heading', 'Before your visit'],
        ['paragraph', "Bring your insurance card and a list of medicines you take.\nArrive 15 minutes early."],
        ['paragraph', 'Photo ID'],
        ['paragraph', 'Referral, if you have one'],
        ['paragraph', "Ask your doctor's office"],
        ['paragraph', '"The staff were wonderful."'],
        ['code', "echo 'hi';"],
        ['paragraph', 'Service'],
        ['paragraph', 'Cost'],
        ['paragraph', 'Call us if your symptoms get worse.'],
        ['paragraph', 'We are open every day.'],
        ['paragraph', 'Inside an extension node nobody here has heard of.'],
    ]);
});

it('takes the tree as the array Statamic stores, as a ProseMirror doc, or as JSON, and reads them alike', function () {
    $json = readFixture('bard.json');
    $array = json_decode($json, true);

    $fromJson = outline((new BardExtractor)->extract($json));

    expect(outline((new BardExtractor)->extract($array)))->toBe($fromJson);
    expect(outline((new BardExtractor)->extract(['type' => 'doc', 'content' => $array])))->toBe($fromJson);
    expect(outline((new BardExtractor)->extract($array[0])))->toBe([$fromJson[0]]);
});

it('records where each block sits, in the same words the HTML extractor uses', function () {
    $blocks = (new BardExtractor)->extract(readFixture('bard.json'))->blocks;

    expect($blocks[0]->origin->tag)->toBe('h2');
    expect($blocks[0]->level)->toBe(2);
    expect($blocks[0]->origin->path)->toBe('0');

    // Bard always puts a list item's text in a paragraph inside the item.
    expect($blocks[2]->origin->ancestors)->toBe(['ul', 'li']);
    expect($blocks[2]->isListItem())->toBeTrue();
    expect($blocks[4]->origin->ancestors)->toBe(['ul', 'li', 'ul', 'li']);
    expect($blocks[4]->origin->path)->toBe('2.1.1.0.0');

    expect($blocks[5]->isQuoted())->toBeTrue();
    expect($blocks[6]->isCode())->toBeTrue();
    expect($blocks[7]->origin->ancestors)->toBe(['table', 'tr', 'th']);
    expect($blocks[7]->isInTable())->toBeTrue();
    expect($blocks[8]->origin->ancestors)->toBe(['table', 'tr', 'td']);
    expect($blocks[11]->origin->ancestors)->toBe(['details']);   // an extension's node keeps its own name
});

it('walks the Bard fields nested inside an enabled set and records the set\'s handle and path', function () {
    $blocks = (new BardExtractor)->extract(readFixture('bard.json'))->blocks;

    expect($blocks[9]->origin->sets)->toBe(['callout']);
    expect($blocks[9]->isInSet('callout'))->toBeTrue();
    expect($blocks[9]->origin->ancestors)->toBe(['set']);
    expect($blocks[9]->origin->path)->toBe('8.body.0');
    expect($blocks[10]->origin->path)->toBe('8.body.1');
});

it('skips a disabled set entirely', function () {
    expect((new BardExtractor)->extract(readFixture('bard.json'))->text())->not->toContain('disabled');
});

it('leaves a set\'s plain string fields to the host, which knows which of them are prose', function () {
    $text = (new BardExtractor)->extract(readFixture('bard.json'))->text();

    expect($text)->not->toContain('plain string field');
    expect($text)->not->toContain('example.test');
});

it('is not fooled by a replicator row, a text node with no text, a leaf node, or something that is not a node', function () {
    $text = (new BardExtractor)->extract(readFixture('bard.json'))->text();

    expect($text)->not->toContain('replicator');
    expect($text)->not->toContain('not a node');
    expect((new BardExtractor)->extract([['type' => 'image', 'attrs' => ['alt' => 'A clinic']], ['type' => 'horizontalRule']])->isEmpty())->toBeTrue();
});

it('folds a newline inside a text node to a space, as the editor shows it, and keeps a hard break as a break', function () {
    $document = (new BardExtractor)->extract([[
        'type' => 'paragraph',
        'content' => [
            ['type' => 'text', 'text' => "one\ntwo"],
            ['type' => 'hardBreak'],
            ['type' => 'text', 'text' => 'three'],
        ],
    ]]);

    expect($document->text())->toBe("one two\nthree");
});

it('clamps a heading level to what HTML has', function () {
    $blocks = (new BardExtractor)->extract([
        ['type' => 'heading', 'attrs' => ['level' => 9], 'content' => [['type' => 'text', 'text' => 'Deep']]],
        ['type' => 'heading', 'content' => [['type' => 'text', 'text' => 'Unstated']]],
    ])->blocks;

    expect([$blocks[0]->level, $blocks[0]->origin->tag])->toBe([6, 'h6']);
    expect([$blocks[1]->level, $blocks[1]->origin->tag])->toBe([1, 'h1']);
});

it('produces no blocks for an empty value', function () {
    expect((new BardExtractor)->extract([])->isEmpty())->toBeTrue();
    expect((new BardExtractor)->extract('')->isEmpty())->toBeTrue();
    expect((new BardExtractor)->extract('[]')->isEmpty())->toBeTrue();
});

it('refuses a string that is not JSON, which is probably HTML meant for the other extractor', function () {
    (new BardExtractor)->extract('<p>Hello</p>');
})->throws(InvalidArgumentException::class, 'Bard node tree');

it('does not lose a text node that somehow sits at the top level', function () {
    expect(outline((new BardExtractor)->extract([['type' => 'text', 'text' => 'Loose words']])))->toBe([['other', 'Loose words']]);
});

it('leaves out text carrying an ignored mark, which is how Bard does inline code', function () {
    $tree = [[
        'type' => 'paragraph',
        'content' => [
            ['type' => 'text', 'text' => 'Run '],
            ['type' => 'text', 'text' => 'composer install', 'marks' => [['type' => 'code']]],
            ['type' => 'text', 'text' => ' first, ', 'marks' => [['type' => 'bold']]],
            ['type' => 'text', 'text' => 'then go.'],
        ],
    ]];

    expect((new BardExtractor)->extract($tree)->text())->toBe('Run composer install first, then go.');
    expect((new BardExtractor(ignoredMarks: ['code']))->extract($tree)->text())->toBe('Run first, then go.');
});
