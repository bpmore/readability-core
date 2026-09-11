<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Text;

use DaveChild\TextStatistics\Syllables;

/**
 * Syllables by davechild/textstatistics, the counter behind most PHP
 * readability tools, itself a port of Perl's Lingua::EN::Syllable:
 * vowel groups, corrected by lists of prefixes, suffixes and exceptions.
 *
 * Two things are done to a word before the library sees it, because the
 * library keeps only the letters A to Z:
 *
 * - accents come off, so "café" counts as "cafe" and not as "caf";
 * - an initialism with no vowel in it ("CDC", "HTML") is read letter by
 *   letter, which is how it is said aloud, rather than as one syllable.
 *
 * The library's last release was in 2018 and it calls utf8_decode(),
 * deprecated since PHP 8.2. The notice is contained here, in the one
 * place the library is called, so the host's log does not carry one line
 * per word. When PHP removes the function this class stops working and
 * the interface is where the replacement goes.
 */
final class TextStatisticsSyllableCounter implements SyllableCounter
{
    public function syllables(string $word): int
    {
        $word = Diacritics::strip(trim($word));

        if (preg_match('/[A-Za-z]/', $word) !== 1) {
            return 0;
        }

        if (preg_match('/^[A-Z]{2,}$/', $word) === 1 && preg_match('/[AEIOUY]/', $word) !== 1) {
            return strlen($word);
        }

        set_error_handler(static fn (): bool => true, E_DEPRECATED);

        try {
            return max(1, (int) Syllables::syllableCount($word));
        } finally {
            restore_error_handler();
        }
    }
}
