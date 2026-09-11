<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Exclusion;

use Bpmore\ReadabilityCore\Text\Block;

/**
 * Anything inside a Bard set with one of the given handles: a code set, a
 * citation set, an embed, whatever the site has decided is not its prose.
 */
final class IgnoredSets extends PerBlock
{
    /**
     * @param  list<string>  $handles
     */
    public function __construct(private readonly array $handles) {}

    protected function keep(Block $block): ?Block
    {
        return $this->handles !== [] && $block->isInSet(...$this->handles) ? null : $block;
    }
}
