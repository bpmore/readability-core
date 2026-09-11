<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Extraction;

use Bpmore\ReadabilityCore\Text\Document;
use InvalidArgumentException;

/**
 * Blocks from plain text: a textarea, a text field, a set's caption. A blank
 * line separates paragraphs, as it does everywhere plain text is written;
 * a single newline is kept as the hard break it is.
 */
final class PlainTextExtractor implements Extractor
{
    public function extract(string|array $source): Document
    {
        if (! is_string($source)) {
            throw new InvalidArgumentException('PlainTextExtractor reads a string.');
        }

        $blocks = [];
        $paragraphs = preg_split('/\n[ \t]*\n/', str_replace(["\r\n", "\r"], "\n", $source)) ?: [];

        foreach ($paragraphs as $paragraph) {
            $frame = Frame::root()->child('p');
            $frame->text = $paragraph;
            $frame->flush($blocks);
        }

        return new Document($blocks);
    }
}
