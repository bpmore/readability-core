<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Exclusion;

use Bpmore\ReadabilityCore\Text\Block;
use Bpmore\ReadabilityCore\Text\English;

/**
 * Proper names and titles. WCAG SC 3.1.5 measures reading level "after
 * removal of proper names and titles", because "Massachusetts" cannot be
 * made simpler and a page should not be graded on it.
 *
 * This is for the formulas only. A sentence with a long name in it is
 * still a long sentence, the acronym rule needs the acronyms, and the
 * jargon dictionary needs every word, so the rules see the text before
 * this runs: `Pipeline::without(ProperNames::class)`.
 *
 * There is no dictionary of names, and no tagger; there is
 * capitalisation, and the rule is to err toward leaving a word in. What
 * goes:
 *
 * - a run of capitalised words in the middle of a sentence, connectors
 *   included ("University of Arkansas for Medical Sciences");
 * - a run led by an honorific, anywhere ("Dr. Smith", "St. Louis");
 * - an acronym or initialism, anywhere ("CDC", "U.S.", "COVID-19");
 * - at the start of a sentence, a capitalised word only when another one
 *   follows it and it is not a word sentences ordinarily start with.
 *
 * What stays: a single capitalised word that starts a sentence (it is
 * probably just the first word), "I" and its contractions, and every word
 * of a block written in title case (a heading, a two-word list item),
 * where capitals mean nothing.
 */
final class ProperNames extends PerBlock
{
    private const TITLE = 't';

    private const CAPITALISED = 'c';

    private const ACRONYM = 'a';

    private const CONNECTOR = 'k';

    /** A capitalised abbreviation that follows a name but never leads one: "Inc.", "Ltd.", "Jr." is a title. */
    private const SUFFIX = 's';

    private const OTHER = 'o';

    protected function keep(Block $block): ?Block
    {
        if ($block->isHeading()) {
            return $block;
        }

        $lines = array_map($this->line(...), explode("\n", $block->text));
        $text = Tidy::text(implode("\n", $lines));

        return $text === $block->text ? $block : $block->withText($text);
    }

    /**
     * One line, with its names taken out. A line is the unit because a
     * hard break ends a sentence as surely as a period does.
     */
    private function line(string $line): string
    {
        $tokens = array_values(array_filter(explode(' ', $line), static fn (string $token): bool => $token !== ''));
        $count = count($tokens);

        if ($count === 0 || $this->isTitleCase($tokens)) {
            return $line;
        }

        $kind = array_map($this->kind(...), $tokens);
        $ends = array_map($this->endsSentence(...), $tokens);
        $removed = array_fill(0, $count, false);

        for ($i = 0; $i < $count; $i++) {
            if (! $this->startsName($tokens, $kind, $ends, $i)) {
                continue;
            }

            // Extend the run: more capitalised words, acronyms or titles,
            // or a connector with one of those after it, never across the
            // end of a sentence.
            $end = $i;

            for ($j = $i + 1; $j < $count && ! $ends[$j - 1]; $j++) {
                if (in_array($kind[$j], [self::CAPITALISED, self::ACRONYM, self::TITLE, self::SUFFIX], true)) {
                    $end = $j;
                } elseif ($kind[$j] === self::CONNECTOR && isset($kind[$j + 1]) && in_array($kind[$j + 1], [self::CAPITALISED, self::ACRONYM], true)) {
                    continue;
                } else {
                    break;
                }
            }

            for ($j = $i; $j <= $end; $j++) {
                $removed[$j] = true;
            }

            $i = $end;
        }

        $out = [];

        foreach ($tokens as $i => $token) {
            if (! $removed[$i]) {
                $out[] = $token;

                continue;
            }

            // What the name wore stays: the bracket that opened before it
            // and the punctuation that closed after it, except the period
            // of an abbreviation, which was the abbreviation's own.
            $leading = ($removed[$i - 1] ?? false) ? '' : $this->leading($token);
            $trailing = ($removed[$i + 1] ?? false) ? '' : $this->trailing($token, dropPeriod: in_array($kind[$i], [self::TITLE, self::SUFFIX], true) || English::isDotted($this->core($token)));

            $out[] = $leading.Tidy::GAP.$trailing;
        }

        return implode(' ', $out);
    }

    /**
     * @param  list<string>  $tokens
     * @param  list<string>  $kind
     * @param  list<bool>  $ends
     */
    private function startsName(array $tokens, array $kind, array $ends, int $i): bool
    {
        if ($kind[$i] === self::TITLE || $kind[$i] === self::ACRONYM) {
            return true;
        }

        if ($kind[$i] !== self::CAPITALISED) {
            return false;
        }

        if ($i > 0 && ! $ends[$i - 1] && ! $this->opensQuote($tokens[$i])) {
            return true;
        }

        // At the start of a sentence, only with company, and only when it
        // is not just how the sentence begins.
        return ! in_array(strtolower($this->core($tokens[$i])), English::OPENERS, true)
            && isset($kind[$i + 1])
            && in_array($kind[$i + 1], [self::CAPITALISED, self::ACRONYM], true);
    }

    private function kind(string $token): string
    {
        $core = $this->core($token);
        $lower = strtolower($core);

        if ($core === '' || preg_match('/^I(?:\'(?:m|ve|ll|d))?$/', $core) === 1) {
            return self::OTHER;
        }

        if (in_array($lower, English::TITLES, true) && ctype_upper($core[0])) {
            return self::TITLE;
        }

        if (in_array($lower, English::ABBREVIATIONS, true)) {
            return ctype_upper($core[0]) ? self::SUFFIX : self::OTHER;
        }

        if (preg_match('/^[A-Z](?:[A-Z0-9&-]|\.(?=[A-Z]))*(?:\'s)?$/', $core) === 1 && preg_match_all('/[A-Z]/', $core) >= 2) {
            return self::ACRONYM;
        }

        if (preg_match('/^[A-Z][A-Za-z\'-]*[a-z][A-Za-z\'-]*$/', $core) === 1) {
            return self::CAPITALISED;
        }

        if (in_array($lower, English::CONNECTORS, true)) {
            return self::CONNECTOR;
        }

        return self::OTHER;
    }

    /**
     * Whether the sentence ends with this token. A period, a question or
     * exclamation mark, or a colon, which is where a capital starts a new
     * clause; unless the period is an abbreviation's own: "Dr.", "e.g.",
     * "U.S.".
     */
    private function endsSentence(string $token): bool
    {
        if (preg_match('/[.!?:]/', $this->trailing($token, dropPeriod: false)) !== 1) {
            return false;
        }

        $core = $this->core($token);

        return ! in_array(strtolower($core), [...English::TITLES, ...English::ABBREVIATIONS], true) && ! English::isDotted($core);
    }

    private function opensQuote(string $token): bool
    {
        return str_contains($this->leading($token), '"') || str_contains($this->leading($token), '\'');
    }

    /**
     * Every word is capitalised except the few a title leaves small ("of",
     * "and", "the"), and nothing ends it like a sentence: a label, a
     * two-word list item, a heading that was not marked up as one.
     * Capitals mean nothing here.
     *
     * @param  list<string>  $tokens
     */
    private function isTitleCase(array $tokens): bool
    {
        if (preg_match('/[.!?]["\')\]]*$/', $tokens[count($tokens) - 1]) === 1) {
            return false;
        }

        $words = 0;

        foreach ($tokens as $token) {
            $core = $this->core($token);

            if ($core === '' || in_array(strtolower($core), English::SMALL_IN_TITLES, true) || preg_match('/\p{L}/u', $core) !== 1) {
                continue;
            }

            if (! ctype_upper($core[0])) {
                return false;
            }

            $words++;
        }

        return $words >= 2;
    }

    /**
     * A token is what opened before the word, the word, and what closed
     * after it: `("Smith's).` is `("`, `Smith's`, `).`. The word keeps a
     * period only when it is inside it ("U.S", "Ph.D", "e.g").
     */
    private function core(string $token): string
    {
        return (string) preg_replace('/^[("\'\[]+|[.,;:!?)"\'\]]+$/', '', $token);
    }

    private function leading(string $token): string
    {
        preg_match('/^[("\'\[]+/', $token, $m);

        return $m[0] ?? '';
    }

    private function trailing(string $token, bool $dropPeriod): string
    {
        preg_match('/[.,;:!?)"\'\]]+$/', $token, $m);
        $trailing = $m[0] ?? '';

        return $dropPeriod ? (string) preg_replace('/^\./', '', $trailing) : $trailing;
    }
}
