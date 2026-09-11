<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Text;

/**
 * Splits a block's text into sentences.
 *
 * Every formula divides by the number of sentences, so a segmenter that
 * breaks "Dr. Smith" in two puts every score off. This is an interface so
 * a better one can be dropped in; what ships is EnglishSegmenter, chosen
 * over ICU and the Packagist options after they were measured against the
 * same traps (the notes in PROGRESS.md have the scores).
 */
interface Segmenter
{
    /**
     * The sentences of one block, in order. A block boundary is a sentence
     * boundary, so callers segment block by block; a hard break inside the
     * block is one too. A fragment with no letter or digit in it ("...")
     * is not a sentence and is not returned.
     *
     * @return list<Sentence>
     */
    public function sentences(string $text): array;
}
