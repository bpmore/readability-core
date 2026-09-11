<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Formula;

/**
 * SMOG (McLaughlin, 1969): polysyllables per thirty sentences, in years of
 * schooling. The formula health literacy work most often cites, and the
 * strictest of the four. It was built for samples of thirty sentences;
 * on less it still computes, less reliably, which is one reason a band
 * is shown and not a number.
 *
 *     1.0430 × √(polysyllables × 30 / sentences) + 3.1291
 */
final class Smog implements Formula
{
    public function name(): string
    {
        return 'SMOG';
    }

    public function grade(Counts $counts): float
    {
        Guard::gradable($counts);

        return 1.0430 * sqrt($counts->polysyllables * 30 / $counts->sentences) + 3.1291;
    }
}
