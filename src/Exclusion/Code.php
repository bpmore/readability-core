<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Exclusion;

use Bpmore\ReadabilityCore\Text\Block;

/**
 * Code blocks and preformatted text. Nobody reads `$foo->bar()` as a
 * sentence, and a syllable counter that tries produces a number that
 * means nothing.
 *
 * Inline code is inside its paragraph by the time this runs. To leave it
 * out too, give the extractors the constants below.
 */
final class Code extends PerBlock
{
    /** For HtmlExtractor's `ignoredTags`. */
    public const INLINE_TAGS = ['code', 'kbd', 'samp', 'var'];

    /** For BardExtractor's `ignoredMarks`. */
    public const INLINE_MARKS = ['code'];

    protected function keep(Block $block): ?Block
    {
        return $block->isCode() ? null : $block;
    }
}
