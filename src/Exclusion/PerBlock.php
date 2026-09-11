<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Exclusion;

use Bpmore\ReadabilityCore\Text\Block;
use Bpmore\ReadabilityCore\Text\Document;

/**
 * An exclusion decided one block at a time, with no need of the others.
 */
abstract class PerBlock implements Exclusion
{
    /** The block as it should count: as it was, with less text, or null to drop it. */
    abstract protected function keep(Block $block): ?Block;

    public function apply(Document $document): Document
    {
        $kept = [];

        foreach ($document->blocks as $block) {
            $block = $this->keep($block);

            if ($block instanceof Block && $block->text !== '') {
                $kept[] = $block;
            }
        }

        return new Document($kept);
    }
}
