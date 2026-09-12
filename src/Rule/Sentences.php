<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Rule;

use Bpmore\ReadabilityCore\Text\Document;
use Bpmore\ReadabilityCore\Text\Segmenter;
use Bpmore\ReadabilityCore\Text\Sentence;
use Bpmore\ReadabilityCore\Text\Words;

/**
 * Every sentence in a document with where it is and how long it is:
 * what most rules walk. Shared so that no rule segments its own way.
 *
 * @internal
 */
final class Sentences
{
    /**
     * @return iterable<array{int, Sentence, int}> block index, the sentence, its word count
     */
    public static function of(Document $document, Segmenter $segmenter): iterable
    {
        foreach ($document->blocks as $index => $block) {
            foreach ($segmenter->sentences($block->text) as $sentence) {
                yield [$index, $sentence, count(Words::of($sentence->text))];
            }
        }
    }

    public static function location(int $block, Sentence $sentence): Location
    {
        return new Location($block, $sentence->offset, strlen($sentence->text));
    }
}
