<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Extraction;

use Bpmore\ReadabilityCore\Text\Document;

/**
 * Turns a field's value into the blocks of text a reader would read.
 *
 * One implementation per shape a value can have: HTML, a Bard node tree,
 * plain text. Markdown is deliberately not one of them. The host renders it
 * with its own parser and its own extensions, and hands the HTML here; a
 * second parser in this package would only ever disagree with the first.
 */
interface Extractor
{
    /**
     * @param  string|array<mixed>  $source
     *
     * @throws \InvalidArgumentException when the value is not the shape this extractor reads
     */
    public function extract(string|array $source): Document;
}
