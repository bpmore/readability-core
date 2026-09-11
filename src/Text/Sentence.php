<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Text;

/**
 * One sentence of a block, and where in the block it starts, so a finding
 * about it can be pointed at.
 */
final class Sentence
{
    public function __construct(
        public readonly string $text,
        /** Byte offset of the first character within the block's text. */
        public readonly int $offset,
    ) {}
}
