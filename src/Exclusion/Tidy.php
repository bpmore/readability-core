<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Exclusion;

/**
 * Closes the gaps that taking spans out of a sentence leaves. "I met
 * Dr. Smith at 501-686-7000." with the name and the number gone is
 * "I met  at ." and should be "I met at."; a sentence counter must still
 * see one sentence, and a word counter must not see a stray period as a
 * word.
 *
 * An exclusion puts GAP where a span was, rather than nothing, so that the
 * comma that belonged to the span ("at 4301 W. Markham St., Little Rock")
 * can go with it.
 *
 * @internal
 */
final class Tidy
{
    public const GAP = "\u{1}";

    public static function text(string $text): string
    {
        $lines = [];

        foreach (explode("\n", $text) as $line) {
            $line = (string) preg_replace('/\s*,?\s*'.self::GAP.'\s*,?/u', ' ', $line);
            $line = (string) preg_replace('/\(\s*\)|\[\s*\]|"\s*"|\'\s*\'/', '', $line);   // brackets and quotes left empty
            $line = (string) preg_replace('/[^\S\n]+/', ' ', $line);
            $line = (string) preg_replace('/\s+([.,;:!?)\]])/', '$1', $line);           // no space before closing punctuation
            $line = (string) preg_replace('/([(\[])\s+/', '$1', $line);                  // or after opening
            $line = (string) preg_replace('/([,;:])(?:\s*[,;:])+/', '$1', $line);        // "Smith, , and" is one comma
            $line = (string) preg_replace('/\s*[,;:]\s*([.!?])/', '$1', $line);            // "at,." ends with the period alone
            $line = trim((string) preg_replace('/^[\s,;:]+/', '', $line));               // nothing leads with a comma

            // A line with no letter or digit left in it said only what was removed.
            if (preg_match('/[\p{L}\p{N}]/u', $line) === 1) {
                $lines[] = $line;
            }
        }

        return implode("\n", $lines);
    }
}
