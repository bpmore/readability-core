<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Exclusion;

use Bpmore\ReadabilityCore\Text\Block;

/**
 * Block quotations. The score is of the author, not of the person quoted,
 * and the attribution under the quote goes with it.
 */
final class Quotes extends PerBlock
{
    protected function keep(Block $block): ?Block
    {
        return $block->isQuoted() ? null : $block;
    }
}
