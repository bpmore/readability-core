<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Exclusion;

use Bpmore\ReadabilityCore\Text\Block;

/**
 * Postal addresses and phone numbers. "4301 W. Markham St., Little Rock,
 * AR 72205" is a place, not a sentence, and "501-686-7000" has no
 * syllables anyone could simplify.
 *
 * An `<address>` element goes whole. Elsewhere the address is cut out of
 * the sentence around it, which stays: "Visit us at 4301 W. Markham St."
 * becomes "Visit us at." and still counts as one short sentence.
 *
 * The patterns know the shapes of English-speaking countries' addresses
 * (a street line, a PO box, a US city-state-zip, a UK or Canadian
 * postcode) and of North American and international phone numbers. That
 * is the English-only scope of the whole engine, not an oversight.
 */
final class Addresses extends PerBlock
{
    private const STREET_TYPES = 'St|Street|Ave|Avenue|Rd|Road|Blvd|Boulevard|Dr|Drive|Ln|Lane|Way|Pkwy|Parkway|Hwy|Highway|Ct|Court|Pl|Place|Cir|Circle|Ter|Terrace|Trl|Trail|Sq|Square|Loop|Row|Plaza|Pike|Turnpike|Expy|Expressway|Fwy|Freeway';

    private const UNIT = '(?:,?\s*(?:Suite|Ste\.?|Unit|Apt\.?|Apartment|#|Floor|Fl\.?|Bldg\.?|Building|Room|Rm\.?)\s*[\w-]+)?';

    /** @var list<string> */
    private const SPANS = [
        // 4301 W. Markham St., Suite 200
        '/\b\d{1,6}[A-Za-z]?\s+(?:(?:[NSEW]|North|South|East|West)\.?\s+)?(?:[A-Z0-9][\w.\'-]*\s+){1,4}(?:'.self::STREET_TYPES.')\b\.?'.self::UNIT.'/',
        // P.O. Box 250
        '/\bP\.?\s?O\.?\s+Box\s+[\w-]+/i',
        // Little Rock, AR 72205-7199, or Ottawa, ON K1A 0B1
        '/\b[A-Z][a-zA-Z.\'-]+(?:\s+[A-Z][a-zA-Z.\'-]+){0,3},\s*[A-Z]{2}\.?\s+(?:\d{5}(?:-\d{4})?|[A-Z]\d[A-Z]\s?\d[A-Z]\d)\b/',
        // SW1A 1AA, or M1 1AE
        '/\b[A-Z]{1,2}\d[A-Z\d]?\s*\d[A-Z]{2}\b/',
        // K1A 0B1
        '/\b[A-Z]\d[A-Z]\s?\d[A-Z]\d\b/',
        // (501) 686-7000, 501.686.7000, +1 501 686 7000, with an extension
        '/(?<![\w-])(?:\+?1[\s.-]?)?(?:\(\d{3}\)|\d{3})[\s.-]?\d{3}[\s.-]?\d{4}(?:\s*(?:ext\.?|x)\s*\d{1,5})?(?![\w-])/i',
        // 1-800-FLU-SHOT
        '/\b(?:1[\s.-]?)?(?:800|888|877|866|855|844|833)[\s.-]?[A-Z0-9]{3}[\s.-]?[A-Z0-9]{4}\b/',
        // +44 20 7946 0958
        '/(?<![\w-])\+\d{1,3}(?:[\s.-]?\(?\d{1,5}\)?){2,6}(?![\w-])/',
    ];

    protected function keep(Block $block): ?Block
    {
        if ($block->origin->within('address')) {
            return null;
        }

        $text = (string) preg_replace(self::SPANS, Tidy::GAP, $block->text);

        return $text === $block->text ? $block : $block->withText(Tidy::text($text));
    }
}
