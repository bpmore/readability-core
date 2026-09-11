<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Text;

/**
 * How many syllables a word has when read aloud.
 *
 * Syllable counting is the genuinely hard part of every readability
 * formula, and the part worth taking from a library. It is an interface
 * so the library can be swapped without touching the formulas, which
 * are arithmetic and stay in this package.
 */
interface SyllableCounter
{
    /**
     * Syllables in one word: a token with no whitespace in it. Zero for a
     * token with no letters ("2024", "—"); never less than one otherwise.
     */
    public function syllables(string $word): int;
}
