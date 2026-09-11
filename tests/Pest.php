<?php

declare(strict_types=1);

use Bpmore\ReadabilityCore\Text\Block;
use Bpmore\ReadabilityCore\Text\BlockKind;
use Bpmore\ReadabilityCore\Text\Document;
use Bpmore\ReadabilityCore\Text\Origin;

// Nothing is booted here on purpose. The engine takes text and returns
// findings; a suite that boots no framework for it cannot grow a dependency
// on something booted, which is the boundary the arch test enforces.

function readFixture(string $name): string
{
    return (string) file_get_contents(__DIR__.'/__fixtures__/'.$name);
}

/**
 * A document as a list of `[kind, text]` pairs: what a reader would get,
 * in order, compact enough to assert against whole.
 *
 * @return list<array{string, string}>
 */
function outline(Document $document): array
{
    return array_map(static fn ($block): array => [$block->kind->value, $block->text], $document->blocks);
}

/**
 * A document of paragraphs from plain strings, for exclusions that only
 * look at text. One block per string; a newline inside one is a hard break.
 */
function paragraphs(string ...$texts): Document
{
    return new Document(array_map(
        static fn (string $text) => new Block(BlockKind::Paragraph, $text, new Origin('p')),
        $texts,
    ));
}

/** The texts of a document, in order. @return list<string> */
function texts(Document $document): array
{
    return array_map(static fn ($block): string => $block->text, $document->blocks);
}
