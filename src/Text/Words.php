<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Text;

/**
 * The words of a piece of text, as a word counter would count them: split
 * on whitespace, the punctuation around each token taken off, and only
 * tokens with a letter or a digit left in them. "Walk-ins" is one word,
 * "e.g." is one word, "—" is none, "2021" is one.
 */
final class Words
{
    /**
     * @return list<string>
     */
    public static function of(string $text): array
    {
        $words = [];

        foreach (preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
            $word = (string) preg_replace('/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/u', '', $token);

            if ($word !== '') {
                $words[] = $word;
            }
        }

        return $words;
    }

    /** Letters and digits in a word: what Coleman-Liau means by a character. */
    public static function characters(string $word): int
    {
        return preg_match_all('/[\p{L}\p{N}]/u', $word);
    }
}
