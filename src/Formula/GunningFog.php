<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Formula;

/**
 * Gunning Fog Index (Gunning, 1952): sentence length and the share of
 * complex words, in years of schooling. Tally applies Gunning's own
 * exclusions when it counts the complex words.
 *
 *     0.4 × ((words / sentences) + 100 × (complex words / words))
 */
final class GunningFog implements Formula
{
    public function name(): string
    {
        return 'Gunning Fog';
    }

    public function grade(Counts $counts): float
    {
        Guard::gradable($counts);

        return 0.4 * ($counts->wordsPerSentence() + 100 * $counts->complexWords / $counts->words);
    }
}
