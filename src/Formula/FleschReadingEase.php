<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Formula;

/**
 * Flesch Reading Ease (Flesch, 1948): the same two ratios as the grade,
 * on a scale where higher is easier and 60–70 is plain English. Not a
 * grade, so not a Formula; kept because people know the number and ask
 * for it.
 *
 *     206.835 − 1.015 × (words / sentences) − 84.6 × (syllables / words)
 */
final class FleschReadingEase
{
    public function score(Counts $counts): float
    {
        Guard::gradable($counts);

        return 206.835 - 1.015 * $counts->wordsPerSentence() - 84.6 * $counts->syllablesPerWord();
    }
}
