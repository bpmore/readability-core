<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Extraction;

use Bpmore\ReadabilityCore\Text\Block;
use Bpmore\ReadabilityCore\Text\BlockKind;
use Bpmore\ReadabilityCore\Text\Normalizer;
use Bpmore\ReadabilityCore\Text\Origin;

/**
 * The block currently being collected while an extractor walks a tree:
 * where it is, and the text gathered so far. Inline content appends to the
 * open frame; a block-level child closes it and opens its own.
 *
 * @internal shared by the extractors; not part of the package's surface
 */
final class Frame
{
    public string $text = '';

    /**
     * @param  list<string>  $ancestors
     * @param  list<string>  $classes
     * @param  list<string>  $sets
     */
    private function __construct(
        public readonly string $tag,
        public readonly array $ancestors,
        public readonly array $classes,
        public readonly array $sets,
        public readonly ?string $path,
        public readonly int $level,
        private readonly bool $isRoot,
    ) {}

    /**
     * The document itself, before any element has opened. Text that sits
     * here belongs to no block, and gets `body` as its tag; the root is never
     * recorded as an ancestor of anything.
     */
    public static function root(): self
    {
        return new self('body', [], [], [], null, 0, isRoot: true);
    }

    /**
     * A frame for a block-level element opening inside this one.
     *
     * @param  list<string>  $classes
     * @param  list<string>  $sets
     */
    public function child(string $tag, array $classes = [], int $level = 0, array $sets = [], ?string $path = null): self
    {
        return new self(
            $tag,
            $this->isRoot ? [] : [...$this->ancestors, $this->tag],
            array_values(array_unique([...$this->classes, ...$classes])),
            [...$this->sets, ...$sets],
            $path,
            $level,
            isRoot: false,
        );
    }

    /**
     * Emit what has been collected as a block, if it says anything, and
     * start collecting again. Called when a block-level child opens, so the
     * text before it stands on its own, and when the element closes.
     *
     * @param  list<Block>  $blocks
     */
    public function flush(array &$blocks): void
    {
        $text = Normalizer::normalize($this->text);
        $this->text = '';

        if ($text === '') {
            return;
        }

        $blocks[] = new Block(
            BlockKind::forTag($this->tag),
            $text,
            new Origin($this->tag, $this->ancestors, $this->classes, $this->sets, $this->path),
            $this->level,
        );
    }
}
