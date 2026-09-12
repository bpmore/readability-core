<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Formula;

use Bpmore\ReadabilityCore\Rule\Findings;
use Bpmore\ReadabilityCore\Text\Block;

/**
 * A run of blocks under one heading, with its own grade. A page can
 * average grade 8 while the paragraph that matters sits at 16, and only
 * this view shows it.
 */
final class Section
{
    /**
     * @param  ?Block  $heading  null for whatever comes before the first heading
     * @param  list<int>  $indexes  the blocks' positions in the document the section was cut from, heading included
     * @param  list<Block>  $blocks  the blocks themselves, heading included
     */
    public function __construct(
        public readonly ?Block $heading,
        public readonly array $indexes,
        public readonly array $blocks,
        public readonly Counts $counts,
    ) {}

    /** The heading's text, or an empty string for the lead. */
    public function title(): string
    {
        return $this->heading?->text ?? '';
    }

    /** The heading's level, 1 to 6, or 0 for the lead. */
    public function level(): int
    {
        return $this->heading?->level ?? 0;
    }

    public function isLead(): bool
    {
        return $this->heading === null;
    }

    /** Nothing to grade: a heading with no prose under it. */
    public function isEmpty(): bool
    {
        return $this->counts->isEmpty();
    }

    public function grades(): ?Grades
    {
        return Grades::of($this->counts);
    }

    public function band(): ?Band
    {
        return $this->grades()?->band();
    }

    public function contains(int $blockIndex): bool
    {
        return in_array($blockIndex, $this->indexes, true);
    }

    /** The findings that point into this section. Findings about the whole document are nobody's section. */
    public function findings(Findings $findings): Findings
    {
        return $findings->inBlocks(...$this->indexes);
    }
}
