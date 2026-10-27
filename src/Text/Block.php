<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Text;

/**
 * One run of text that stands on its own: a paragraph, a heading, a list
 * item, a table cell. The unit everything downstream works in. A sentence
 * never crosses a block, a section is a run of blocks under a heading, and
 * an exclusion drops whole blocks.
 *
 * The text is already normalised. A newline in it is a hard break the author
 * put there (`<br>`, a Bard `hardBreak`), never formatting from the source.
 *
 * A block an extractor made also keeps what the author wrote, before any of
 * that cleaning (`written`), and where each byte of `text` came from in it
 * (`positions`). A rule's finding is a position in `text`; writtenSpan()
 * turns it into a position in what the author wrote, which is what an
 * editor needs to underline the right words. A block built some other way,
 * or changed with withText(), has no positions, and says so with null.
 */
final class Block
{
    public function __construct(
        public readonly BlockKind $kind,
        public readonly string $text,
        public readonly Origin $origin,
        /** Heading level, 1 to 6; zero for anything that is not a heading. */
        public readonly int $level = 0,
        /** The text as the author wrote it, before any cleaning; null when unknown. */
        public readonly ?string $written = null,
        /** Where each byte of `text` came from in `written`; null when unknown. */
        public readonly ?Tracked $positions = null,
    ) {}

    /**
     * The same block, saying less: what an exclusion leaves of it. What the
     * author wrote is kept; where the new text came from is not known, so
     * the positions go.
     */
    public function withText(string $text): self
    {
        return new self($this->kind, $text, $this->origin, $this->level, $this->written);
    }

    /** The same block, its text changed by an operation that kept track. */
    public function withTracked(Tracked $text): self
    {
        return new self($this->kind, $text->text, $this->origin, $this->level, $this->written, $text);
    }

    /**
     * Where a stretch of `text` is in what the author wrote: start and end,
     * in bytes; or null when this block does not know.
     *
     * @return array{int, int}|null
     */
    public function writtenSpan(int $offset, int $length): ?array
    {
        return $this->positions?->span($offset, $length);
    }

    public function isHeading(): bool
    {
        return $this->kind === BlockKind::Heading;
    }

    /** Text the author is quoting rather than writing: a blockquote, or anything inside one. */
    public function isQuoted(): bool
    {
        return $this->origin->within('blockquote');
    }

    /** A code block, or anything inside one. Inline `<code>` is not a block and is not this. */
    public function isCode(): bool
    {
        return $this->origin->within('pre');
    }

    /** A list item's own text, whether it sat directly in the `li` or in a paragraph inside it, as Bard always puts it. */
    public function isListItem(): bool
    {
        return $this->kind === BlockKind::ListItem || in_array($this->origin->parent(), ['li', 'dt'], true);
    }

    public function isInTable(): bool
    {
        return $this->origin->within('table');
    }

    /** Whether the block sits inside a Bard set: any set, or one of the handles given. */
    public function isInSet(string ...$handles): bool
    {
        return $handles === [] ? $this->origin->sets !== [] : array_intersect($handles, $this->origin->sets) !== [];
    }

    /** Whether the block, or anything enclosing it, carries one of the given HTML classes. */
    public function hasClass(string ...$classes): bool
    {
        return array_intersect($classes, $this->origin->classes) !== [];
    }
}
