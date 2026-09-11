<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Exclusion;

use Bpmore\ReadabilityCore\Text\Block;

/**
 * Anything inside an element carrying one of the site's "leave this out"
 * classes. For a `<span>` inside a paragraph this is too late, the text is
 * already in the block; give HtmlExtractor the same classes for that.
 */
final class IgnoredClasses extends PerBlock
{
    /**
     * @param  list<string>  $classes
     */
    public function __construct(private readonly array $classes) {}

    protected function keep(Block $block): ?Block
    {
        return $this->classes !== [] && $block->hasClass(...$this->classes) ? null : $block;
    }
}
