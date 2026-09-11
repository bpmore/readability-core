<?php

declare(strict_types=1);

use Bpmore\ReadabilityCore\Text\Block;
use Bpmore\ReadabilityCore\Text\BlockKind;
use Bpmore\ReadabilityCore\Text\Document;
use Bpmore\ReadabilityCore\Text\Origin;

it('joins its blocks with a blank line, so a block boundary is always a hard stop', function () {
    $document = new Document([
        new Block(BlockKind::Heading, 'Title', new Origin('h1'), 1),
        new Block(BlockKind::Paragraph, 'First.', new Origin('p')),
        new Block(BlockKind::Paragraph, "Second\nwith a break.", new Origin('p')),
    ]);

    expect($document->text())->toBe("Title\n\nFirst.\n\nSecond\nwith a break.");
    expect($document)->toHaveCount(3);
    expect(iterator_to_array($document))->toBe($document->blocks);
    expect($document->isEmpty())->toBeFalse();
});

it('can be empty', function () {
    expect(Document::empty()->isEmpty())->toBeTrue();
    expect(Document::empty()->text())->toBe('');
    expect(Document::empty())->toHaveCount(0);
});

it('maps tags onto the handful of kinds the rules distinguish', function () {
    expect(BlockKind::forTag('h3'))->toBe(BlockKind::Heading);
    expect(BlockKind::forTag('p'))->toBe(BlockKind::Paragraph);
    expect(BlockKind::forTag('li'))->toBe(BlockKind::ListItem);
    expect(BlockKind::forTag('blockquote'))->toBe(BlockKind::Quote);
    expect(BlockKind::forTag('pre'))->toBe(BlockKind::Code);
    expect(BlockKind::forTag('td'))->toBe(BlockKind::Cell);
    expect(BlockKind::forTag('figcaption'))->toBe(BlockKind::Caption);
    expect(BlockKind::forTag('address'))->toBe(BlockKind::Other);
    expect(BlockKind::forTag('div'))->toBe(BlockKind::Other);
});

it('knows what encloses it, by any of the ways the two extractors express it', function () {
    $direct = new Block(BlockKind::Quote, 'q', new Origin('blockquote', ['article']));
    $nested = new Block(BlockKind::Paragraph, 'q', new Origin('p', ['article', 'blockquote']));
    $plain = new Block(BlockKind::Paragraph, 'p', new Origin('p', ['article']));

    expect($direct->isQuoted())->toBeTrue();
    expect($nested->isQuoted())->toBeTrue();
    expect($plain->isQuoted())->toBeFalse();

    // A list item's text sits in the li in HTML and in a paragraph inside
    // the li in Bard. Both are the item.
    expect((new Block(BlockKind::ListItem, 'i', new Origin('li', ['ul'])))->isListItem())->toBeTrue();
    expect((new Block(BlockKind::Paragraph, 'i', new Origin('p', ['ul', 'li'])))->isListItem())->toBeTrue();
    expect($plain->isListItem())->toBeFalse();

    expect((new Block(BlockKind::Code, 'c', new Origin('pre')))->isCode())->toBeTrue();
    expect((new Block(BlockKind::Cell, 'c', new Origin('td', ['table', 'tr'])))->isInTable())->toBeTrue();
    expect((new Block(BlockKind::Paragraph, 'c', new Origin('p', ['table', 'tr', 'th'])))->isInTable())->toBeTrue();
    expect($plain->isInTable())->toBeFalse();
    expect($plain->isHeading())->toBeFalse();
});

it('knows the set and the classes it sits in', function () {
    $block = new Block(BlockKind::Paragraph, 'p', new Origin('p', ['set'], ['entry', 'no-readability'], ['callout'], '3.body.0'));

    expect($block->isInSet())->toBeTrue();
    expect($block->isInSet('callout'))->toBeTrue();
    expect($block->isInSet('code', 'callout'))->toBeTrue();
    expect($block->isInSet('code'))->toBeFalse();
    expect($block->hasClass('no-readability'))->toBeTrue();
    expect($block->hasClass('other', 'entry'))->toBeTrue();
    expect($block->hasClass('other'))->toBeFalse();
    expect($block->origin->parent())->toBe('set');
    expect($block->origin->path)->toBe('3.body.0');

    $bare = new Block(BlockKind::Paragraph, 'p', new Origin('p'));
    expect($bare->isInSet())->toBeFalse();
    expect($bare->hasClass('anything'))->toBeFalse();
    expect($bare->origin->parent())->toBeNull();
});
