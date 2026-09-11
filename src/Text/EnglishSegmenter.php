<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Text;

/**
 * Sentence boundaries by the rules of English punctuation, with the
 * abbreviations that break them.
 *
 * A candidate boundary is a run of `.`, `!` or `?`, any closing quotes or
 * brackets after it, and then whitespace. It is a boundary unless:
 *
 * - a lowercase letter follows, in which case the sentence is going on
 *   ("Acme Corp. and", "wait... what", '"Are you sure?" she asked');
 * - the period belongs to an honorific ("Dr.", "St."), which a name follows;
 * - the period belongs to an initial ("J. K. Rowling");
 * - the period belongs to an abbreviation or a dotted one ("etc.", "Inc.",
 *   "U.S.", "p.m."), and what follows is not a word sentences start with.
 *   "U.S. Food and Drug Administration" runs on; "at 3 p.m. Please be on
 *   time" does not. A digit after one runs on too ("approx. 20", "No. 5").
 *
 * A decimal ("4.50"), a domain ("example.com") or a section number ("2.1")
 * never comes up, because no whitespace follows their period.
 *
 * Measured against the traps in the test file, this got all of them; ICU's
 * sentence iterator, without CLDR's suppressions, broke after every
 * honorific, and the Packagist library broke in both directions.
 */
final class EnglishSegmenter implements Segmenter
{
    private const CANDIDATE = '/([.!?]+)(["\')\]]*)(?=\s)/';

    public function sentences(string $text): array
    {
        $sentences = [];
        $lineOffset = 0;

        foreach (explode("\n", $text) as $line) {
            $start = 0;
            $searchFrom = 0;

            while (preg_match(self::CANDIDATE, $line, $m, PREG_OFFSET_CAPTURE, $searchFrom) === 1) {
                $end = $m[0][1] + strlen($m[0][0]);
                $searchFrom = $end;

                if ($this->isBoundary($m[1][0], substr($line, $start, $m[1][1] - $start), substr($line, $end))) {
                    $this->push($sentences, substr($line, $start, $end - $start), $lineOffset + $start);
                    $start = $end;
                }
            }

            $this->push($sentences, substr($line, $start), $lineOffset + $start);
            $lineOffset += strlen($line) + 1;
        }

        return $sentences;
    }

    /**
     * @param  string  $terminal  the run of `.!?`
     * @param  string  $before  the line up to the terminal
     * @param  string  $after  the line from the whitespace on
     */
    private function isBoundary(string $terminal, string $before, string $after): bool
    {
        // The first thing a reader sees next, past any quote or bracket that
        // opens the next sentence.
        $next = ltrim($after);
        $nextChar = mb_substr((string) preg_replace('/^["\'(\[]+/', '', $next), 0, 1);

        if ($nextChar !== '' && mb_strtolower($nextChar) === $nextChar && mb_strtoupper($nextChar) !== $nextChar) {
            return false;
        }

        if ($terminal !== str_repeat('.', strlen($terminal))) {
            return true;
        }

        // The word the period is attached to, without the brackets or
        // quotes around it: "(ext" is "ext".
        $word = trim((string) preg_replace('/^.*\s/s', '', $before), '"\'()[]');
        $core = strtolower(rtrim($word, '.'));

        if (in_array($core, English::TITLES, true) || preg_match('/^[a-z]$/', $core) === 1) {
            return false;
        }

        if (English::isDotted($core) || in_array($core, English::ABBREVIATIONS, true)) {
            if ($nextChar !== '' && ctype_digit($nextChar)) {
                return false;
            }

            preg_match('/^["\'(\[]*([^\s.,;:!?"\')\]]+)/u', $next, $nm);

            return str_starts_with($next, '"') || in_array(strtolower($nm[1] ?? ''), English::OPENERS, true);
        }

        return true;
    }

    /**
     * @param  list<Sentence>  $sentences
     */
    private function push(array &$sentences, string $fragment, int $offset): void
    {
        $trimmed = ltrim($fragment);
        $offset += strlen($fragment) - strlen($trimmed);
        $trimmed = rtrim($trimmed);

        if (preg_match('/[\p{L}\p{N}]/u', $trimmed) === 1) {
            $sentences[] = new Sentence($trimmed, $offset);
        }
    }
}
