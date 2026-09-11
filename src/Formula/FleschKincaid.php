<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Formula;

/**
 * Flesch-Kincaid Grade Level (Kincaid et al., 1975): sentence length and
 * word length, in years of schooling. The one most people have heard of.
 *
 *     0.39 × (words / sentences) + 11.8 × (syllables / words) − 15.59
 */
final class FleschKincaid implements Formula
{
    public function name(): string
    {
        return 'Flesch-Kincaid';
    }

    public function grade(Counts $counts): float
    {
        Guard::gradable($counts);

        return 0.39 * $counts->wordsPerSentence() + 11.8 * $counts->syllablesPerWord() - 15.59;
    }
}
