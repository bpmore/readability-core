<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Rule;

/**
 * Finds words and phrases in text the way the dictionary rules need
 * them found: whole words, any case, a phrase's spaces matching across a
 * hard break, and a single word taking its inflections when asked.
 * Shared so the jargon rule and the filler rule cannot disagree about
 * what "matches".
 *
 * @internal
 */
final class Phrases
{
    /**
     * A pattern for one term. With inflections, "utilize" also finds
     * "utilizes", "utilized" and "utilizing"; "notify" finds "notifies",
     * "notified" and "notifying".
     */
    public static function pattern(string $term, bool $inflections = false): string
    {
        $words = preg_split('/\s+/u', trim($term)) ?: [];

        if (! $inflections || count($words) > 1 || preg_match('/\p{L}$/u', $term) !== 1) {
            return '/(?<![\p{L}\p{N}])'.implode('\s+', array_map(static fn (string $w): string => preg_quote($w, '/'), $words)).'(?![\p{L}\p{N}])/iu';
        }

        $inflected = match (true) {
            str_ends_with($term, 'e') => preg_quote(substr($term, 0, -1), '/').'(?:e|es|ed|ing)',
            str_ends_with($term, 'y') => preg_quote(substr($term, 0, -1), '/').'(?:y|ies|ied|ying)',
            default => preg_quote($term, '/').'(?:s|es|ed|ing)?',
        };

        return '/(?<![\p{L}\p{N}])'.$inflected.'(?![\p{L}\p{N}])/iu';
    }

    /**
     * Every match of every pattern in the text, longest term first, with
     * a shorter term never reported inside a longer one that already
     * matched there.
     *
     * @param  list<array{string, mixed}>  $patterns  pattern, and whatever the caller wants back with a match
     * @return list<array{int, string, mixed}> offset, the text as written, the caller's payload; in reading order
     */
    public static function findAll(string $text, array $patterns): array
    {
        $found = [];
        $taken = [];

        foreach ($patterns as [$pattern, $payload]) {
            preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[0] as [$written, $offset]) {
                $end = $offset + strlen($written);

                foreach ($taken as [$from, $to]) {
                    if ($offset < $to && $end > $from) {
                        continue 2;
                    }
                }

                $taken[] = [$offset, $end];
                $found[] = [$offset, $written, $payload];
            }
        }

        usort($found, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        return $found;
    }
}
