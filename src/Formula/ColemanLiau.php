<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Formula;

/**
 * Coleman-Liau Index (Coleman & Liau, 1975): letters per word and
 * sentences per word, in years of schooling. The one formula here that
 * does not count syllables, which makes it a useful check on the three
 * that do.
 *
 *     0.0588 × L − 0.296 × S − 15.8
 *
 * where L is letters per hundred words and S is sentences per hundred
 * words.
 */
final class ColemanLiau implements Formula
{
    public function name(): string
    {
        return 'Coleman-Liau';
    }

    public function grade(Counts $counts): float
    {
        Guard::gradable($counts);

        $letters = 100 * $counts->characters / $counts->words;
        $sentences = 100 * $counts->sentences / $counts->words;

        return 0.0588 * $letters - 0.296 * $sentences - 15.8;
    }
}
