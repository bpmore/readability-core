<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Text;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * The analysable text of one field, or one page: its blocks, in reading
 * order. What an extractor produces and what everything after it consumes.
 *
 * @implements IteratorAggregate<int, Block>
 */
final class Document implements Countable, IteratorAggregate
{
    /**
     * @param  list<Block>  $blocks
     */
    public function __construct(public readonly array $blocks) {}

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * The whole thing as plain text, one blank line between blocks. For
     * eyeballing and for anything that only wants words; a block boundary
     * is a hard stop, and the blank line keeps it one.
     */
    public function text(): string
    {
        return implode("\n\n", array_map(static fn (Block $block): string => $block->text, $this->blocks));
    }

    public function isEmpty(): bool
    {
        return $this->blocks === [];
    }

    public function count(): int
    {
        return count($this->blocks);
    }

    /**
     * @return Traversable<int, Block>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->blocks);
    }
}
