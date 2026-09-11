<?php

declare(strict_types=1);

use Bpmore\ReadabilityCore\Text\Normalizer;

it('collapses runs of spaces, tabs and carriage returns to one space, and trims the ends', function () {
    expect(Normalizer::normalize("  One   two\t\tthree  \r four  "))->toBe('One two three four');
});

it('keeps a newline the caller put there as a hard break, once, with nothing around it', function () {
    // The extractors write "\n" for <br> and hardBreak; source formatting
    // never reaches here as a bare newline, so one is always deliberate.
    expect(Normalizer::normalize("Call us today  \n  Monday to Friday"))->toBe("Call us today\nMonday to Friday");
    expect(Normalizer::normalize("a\n\n\nb"))->toBe("a\nb");
});

it('turns every kind of space into the plain one', function () {
    // No-break, narrow no-break, thin, figure, ideographic: all one word
    // boundary, or the tokenizer sees "the patient" as one word.
    expect(Normalizer::normalize("the\u{00A0}patient\u{202F}is\u{2009}here\u{2007}now\u{3000}ok"))->toBe('the patient is here now ok');
});

it('treats the Unicode line and paragraph separators as breaks', function () {
    expect(Normalizer::normalize("one\u{2028}two\u{2029}three"))->toBe("one\ntwo\nthree");
});

it('removes characters that take no space and mean nothing to a reader', function () {
    // Soft hyphen, zero-width space and joiners, word joiner, byte order
    // mark. Each one splits a word in two for a counter that cannot see it.
    expect(Normalizer::normalize("\u{FEFF}medi\u{00AD}cation\u{200B}s\u{200C}\u{200D}\u{2060}"))->toBe('medications');
});

it('straightens curly quotes and the apostrophe every word processor emits', function () {
    expect(Normalizer::normalize("\u{201C}Don\u{2019}t\u{201D} \u{2018}stop\u{2019} \u{201E}now\u{201C} 5\u{2032} 6\u{2033}"))
        ->toBe('"Don\'t" \'stop\' "now" 5\' 6"');
});

it('spells out an ellipsis', function () {
    expect(Normalizer::normalize("Wait\u{2026} what"))->toBe('Wait... what');
});

it('leaves dashes, accents and other real content alone', function () {
    expect(Normalizer::normalize('Monday–Friday — café naïve 10–12 co-op'))->toBe('Monday–Friday — café naïve 10–12 co-op');
});

it('composes a decomposed accent when intl is available', function () {
    $decomposed = "cafe\u{0301}";

    expect(Normalizer::normalize($decomposed))->toBe(class_exists(\Normalizer::class) ? "caf\u{00E9}" : $decomposed);
});

it('survives a byte that is not UTF-8 rather than losing the block around it', function () {
    expect(Normalizer::normalize("caf\xE9 au lait"))->toBe('caf? au lait');
});

it('returns an empty string for nothing but whitespace', function () {
    expect(Normalizer::normalize(" \n\t\u{00A0}\u{200B} "))->toBe('');
});
