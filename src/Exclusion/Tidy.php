<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Exclusion;

use Bpmore\ReadabilityCore\Text\Tracked;

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
        return self::track(Tracked::of($text))->text;
    }

    /**
     * The same, keeping where every byte came from.
     */
    public static function track(Tracked $text): Tracked
    {
        $lines = [];

        foreach ($text->split("\n") as [$line, $separator]) {
            $line = $line->replace('/\s*,?\s*'.self::GAP.'\s*,?/u', ' ');
            $line = $line->replace('/\(\s*\)|\[\s*\]|"\s*"|\'\s*\'/', '');   // brackets and quotes left empty
            $line = $line->replace('/[^\S\n]+/', ' ');
            $line = $line->replace('/\s+([.,;:!?)\]])/', '$1');           // no space before closing punctuation
            $line = $line->replace('/([(\[])\s+/', '$1');                  // or after opening
            $line = $line->replace('/([,;:])(?:\s*[,;:])+/', '$1');        // "Smith, , and" is one comma
            $line = $line->replace('/\s*[,;:]\s*([.!?])/', '$1');            // "at,." ends with the period alone
            $line = $line->replace('/^[\s,;:]+/', '')->trim();               // nothing leads with a comma

            // A line with no letter or digit left in it said only what was removed.
            if (preg_match('/[\p{L}\p{N}]/u', $line->text) === 1) {
                $lines[] = [$line, $separator];
            }
        }

        return $text->join($lines);
    }
}
