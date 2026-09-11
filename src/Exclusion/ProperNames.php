<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Exclusion;

use Bpmore\ReadabilityCore\Text\Block;

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
    /** Honorifics and prefixes that lead a name and whose period does not end a sentence. */
    private const TITLES = [
        'dr', 'mr', 'mrs', 'ms', 'mx', 'prof', 'sen', 'rep', 'gov', 'rev', 'hon', 'sr', 'jr', 'st', 'mt', 'ft',
        'gen', 'col', 'capt', 'lt', 'sgt', 'maj', 'cpl', 'pvt', 'cmdr', 'adm', 'pres', 'fr', 'br', 'msgr', 'supt',
        'insp', 'det', 'atty', 'messrs',
    ];

    /** Abbreviations whose period does not end a sentence, and which do not start a name. */
    private const ABBREVIATIONS = [
        'e.g', 'i.e', 'etc', 'vs', 'cf', 'approx', 'no', 'nos', 'inc', 'ltd', 'co', 'corp', 'dept', 'est', 'fig',
        'jan', 'feb', 'mar', 'apr', 'jun', 'jul', 'aug', 'sep', 'sept', 'oct', 'nov', 'dec',
        'mon', 'tue', 'tues', 'wed', 'thu', 'thur', 'thurs', 'fri', 'sat', 'sun', 'a.m', 'p.m', 'ph.d', 'm.d', 'b.a', 'm.a',
    ];

    /** Lowercase words that sit inside a name without being capitalised. */
    private const CONNECTORS = ['of', 'for', 'and', 'the', 'de', 'da', 'di', 'du', 'del', 'della', 'van', 'von', 'der', 'den', 'la', 'le', 'les', 'y', 'e', '&', 'at', 'on', 'in'];

    /**
     * Words a sentence ordinarily starts with. Capitalised at the front of
     * a sentence and followed by a name, they are not part of it: "The
     * World Health Organization", "Visit Little Rock", "Ask Nurse Kelly".
     */
    private const OPENERS = [
        'a', 'an', 'the', 'and', 'but', 'or', 'nor', 'so', 'yet', 'for', 'of', 'in', 'on', 'at', 'to', 'by', 'with', 'from',
        'into', 'onto', 'over', 'under', 'about', 'after', 'before', 'during', 'until', 'while', 'when', 'whenever',
        'where', 'wherever', 'why', 'how', 'what', 'whatever', 'which', 'who', 'whom', 'whose', 'this', 'that', 'these',
        'those', 'it', 'its', 'he', 'she', 'they', 'them', 'his', 'her', 'hers', 'their', 'theirs', 'we', 'us', 'our', 'ours',
        'you', 'your', 'yours', 'my', 'me', 'mine', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'am', 'do', 'does',
        'did', 'have', 'has', 'had', 'will', 'would', 'can', 'could', 'should', 'may', 'might', 'must', 'shall', 'not',
        'no', 'yes', 'if', 'then', 'than', 'as', 'because', 'since', 'although', 'though', 'unless', 'whether', 'also',
        'too', 'very', 'just', 'only', 'even', 'still', 'here', 'there', 'now', 'today', 'tomorrow', 'yesterday',
        'tonight', 'first', 'second', 'third', 'next', 'last', 'finally', 'however', 'meanwhile', 'therefore',
        'otherwise', 'instead', 'again', 'often', 'sometimes', 'usually', 'always', 'never', 'once', 'soon', 'later',
        'please', 'call', 'ask', 'visit', 'bring', 'take', 'get', 'make', 'use', 'see', 'read', 'learn', 'find', 'check',
        'contact', 'schedule', 'book', 'talk', 'tell', 'let', 'keep', 'try', 'go', 'come', 'stay', 'wait', 'remember',
        'note', 'thank', 'thanks', 'welcome', 'meet', 'join', 'help', 'give', 'send', 'show', 'watch', 'avoid', 'stop',
        'start', 'follow', 'choose', 'consider', 'expect', 'know', 'think', 'feel', 'want', 'need', 'like', 'each', 'every',
        'all', 'some', 'many', 'most', 'any', 'both', 'few', 'several', 'other', 'another', 'such', 'one', 'two', 'three',
        'new', 'more', 'less', 'good', 'great', 'best', 'well', 'sorry', 'hello', 'hi', 'dear', 'according', 'unlike',
        'like', 'without', 'within', 'across', 'along', 'among', 'around', 'behind', 'beyond', 'despite', 'except',
        'inside', 'outside', 'through', 'throughout', 'toward', 'towards', 'upon', 'via', 'i\'m', 'i\'ve', 'i\'ll', 'i\'d',
    ];

    /** The words a title leaves in lower case. */
    private const SMALL_IN_TITLES = [
        'a', 'an', 'the', 'and', 'but', 'or', 'nor', 'for', 'of', 'to', 'in', 'on', 'at', 'by', 'with', 'as', 'vs', 'via',
        'from', 'into', 'onto', 'over', 'per', 'up', 'de', 'da', 'di', 'du', 'del', 'van', 'von', 'der', 'la', 'le', 'y', '&',
    ];

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
            $trailing = ($removed[$i + 1] ?? false) ? '' : $this->trailing($token, dropPeriod: in_array($kind[$i], [self::TITLE, self::SUFFIX], true) || $this->isDotted($this->core($token)));

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
        return ! in_array(strtolower($this->core($tokens[$i])), self::OPENERS, true)
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

        if (in_array($lower, self::TITLES, true) && ctype_upper($core[0])) {
            return self::TITLE;
        }

        if (in_array($lower, self::ABBREVIATIONS, true)) {
            return ctype_upper($core[0]) ? self::SUFFIX : self::OTHER;
        }

        if (preg_match('/^[A-Z](?:[A-Z0-9&-]|\.(?=[A-Z]))*(?:\'s)?$/', $core) === 1 && preg_match_all('/[A-Z]/', $core) >= 2) {
            return self::ACRONYM;
        }

        if (preg_match('/^[A-Z][A-Za-z\'-]*[a-z][A-Za-z\'-]*$/', $core) === 1) {
            return self::CAPITALISED;
        }

        if (in_array($lower, self::CONNECTORS, true)) {
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

        return ! in_array(strtolower($core), [...self::TITLES, ...self::ABBREVIATIONS], true) && ! $this->isDotted($core);
    }

    /** "U.S", "a.m", "Ph.D": letters with periods between them, whose last period is theirs. */
    private function isDotted(string $core): bool
    {
        return preg_match('/^(?:[A-Za-z]{1,2}\.)+[A-Za-z]{1,2}$/', $core) === 1;
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

            if ($core === '' || in_array(strtolower($core), self::SMALL_IN_TITLES, true) || preg_match('/\p{L}/u', $core) !== 1) {
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
