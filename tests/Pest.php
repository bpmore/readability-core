<?php

declare(strict_types=1);

use Bpmore\ReadabilityCore\Text\Document;

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
