<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Text;

/**
 * Text as the formulas and the rules want to see it, whatever the field
 * did to it on the way in.
 *
 * Each step below exists because of a specific way it goes wrong otherwise.
 * A no-break space is not a word boundary to a naive tokenizer, so
 * "the&nbsp;patient" is one long word. A curly apostrophe makes "don’t" a
 * different token from "don't", and every word list and syllable counter
 * knows the straight one. A soft hyphen is invisible and splits a word in
 * two. And a newline that reaches here is a break somebody meant: the
 * extractors fold the source's own line wrapping to spaces before the text
 * arrives, and write a newline only for `<br>`, a Bard hard break, or a line
 * of preformatted text. Those are kept, bare and never doubled.
 */
final class Normalizer
{
    /** Characters that take up no width and mean nothing to a reader. */
    private const INVISIBLE = "\u{00AD}\u{200B}\u{200C}\u{200D}\u{2060}\u{FEFF}";

    private const PUNCTUATION = [
        "\u{2018}" => "'",   // left single quotation mark
        "\u{2019}" => "'",   // right single quotation mark, and the apostrophe every word processor emits
        "\u{201A}" => "'",   // single low-9 quotation mark
        "\u{2032}" => "'",   // prime
        "\u{201C}" => '"',   // left double quotation mark
        "\u{201D}" => '"',   // right double quotation mark
        "\u{201E}" => '"',   // double low-9 quotation mark
        "\u{2033}" => '"',   // double prime
        "\u{2026}" => '...', // horizontal ellipsis
    ];

    public static function normalize(string $text): string
    {
        // A stray byte that is not UTF-8 would make every pattern below fail
        // and the whole block vanish. Replaced with "?" instead, which costs
        // one word at most.
        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_scrub($text, 'UTF-8');
        }

        // Composed form, so an "é" typed as two code points is the same word
        // as one typed as one. intl is not required at runtime, so this step
        // is skipped without it; a decomposed accent then miscounts at worst.
        if (class_exists(\Normalizer::class)) {
            $text = \Normalizer::normalize($text, \Normalizer::FORM_C) ?: $text;
        }

        $text = str_replace(mb_str_split(self::INVISIBLE), '', $text);
        $text = strtr($text, self::PUNCTUATION);

        // Every Unicode space (no-break, thin, figure, ideographic) becomes
        // the plain one; the line and paragraph separators become newlines,
        // which is what they are.
        $text = (string) preg_replace('/\p{Zs}/u', ' ', $text);
        $text = (string) preg_replace('/[\x{2028}\x{2029}]/u', "\n", $text);

        // Whitespace collapses to one space, except a newline, which is a hard
        // break and stays: bare, and never doubled.
        $text = (string) preg_replace('/[^\S\n]+/', ' ', $text);
        $text = (string) preg_replace('/ *\n */', "\n", $text);
        $text = (string) preg_replace('/\n{2,}/', "\n", $text);

        return trim($text);
    }
}
