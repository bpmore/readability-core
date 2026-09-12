<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Rule;

/**
 * Where in the document a finding points: which block, and the bytes of
 * its text the finding is about. Enough for a host to highlight the
 * sentence in the editor, using the block's Origin to find the field
 * and, for Bard, the node.
 */
final class Location
{
    public function __construct(
        /** Index of the block in the Document the rule was given. */
        public readonly int $block,
        /** Byte offset within the block's text. */
        public readonly int $offset,
        /** Byte length. */
        public readonly int $length,
    ) {}

    public function end(): int
    {
        return $this->offset + $this->length;
    }
}
