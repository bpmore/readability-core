<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Formula;

/**
 * The tallies every readability formula is arithmetic over. Made once
 * from a document by Tally; a formula never sees the text.
 *
 * Two of these can be added, so a section's counts and a document's are
 * the same kind of thing.
 */
final class Counts
{
    public function __construct(
        public readonly int $sentences,
        public readonly int $words,
        /** Every word has at least one, a number included. */
        public readonly int $syllables,
        /** Letters and digits in the words: Coleman-Liau's characters. */
        public readonly int $characters,
        /** Gunning's "complex" words: three syllables or more, with his exclusions. */
        public readonly int $complexWords,
        /** SMOG's polysyllables: three syllables or more, no exclusions. */
        public readonly int $polysyllables,
    ) {}

    public static function none(): self
    {
        return new self(0, 0, 0, 0, 0, 0);
    }

    public function add(self $other): self
    {
        return new self(
            $this->sentences + $other->sentences,
            $this->words + $other->words,
            $this->syllables + $other->syllables,
            $this->characters + $other->characters,
            $this->complexWords + $other->complexWords,
            $this->polysyllables + $other->polysyllables,
        );
    }

    /** Nothing to grade: no words, or no sentences to put them in. */
    public function isEmpty(): bool
    {
        return $this->words === 0 || $this->sentences === 0;
    }

    public function wordsPerSentence(): float
    {
        return $this->words / $this->sentences;
    }

    public function syllablesPerWord(): float
    {
        return $this->syllables / $this->words;
    }
}
