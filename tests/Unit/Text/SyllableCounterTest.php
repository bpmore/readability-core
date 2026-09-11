<?php

declare(strict_types=1);

use Bpmore\ReadabilityCore\Text\Diacritics;
use Bpmore\ReadabilityCore\Text\SyllableCounter;
use Bpmore\ReadabilityCore\Text\TextStatisticsSyllableCounter;

/**
 * Words with the count a dictionary gives. The library's own heuristic
 * misses ("science" 1, "create" 1, "area" 2, "queue" 2, "naïve" 1) are
 * not here: a test that asserts a wrong number keeps it wrong.
 */
dataset('syllables', [
    ['the', 1], ['a', 1], ['flu', 1], ['eye', 1], ['I', 1],
    ['doctor', 2], ['people', 2], ['table', 2], ['business', 2], ['rhythm', 2],
    ['medicine', 3], ['hospital', 3], ['appointment', 3], ['beautiful', 3], ['idea', 3], ['syllable', 3],
    ['vaccination', 4], ['information', 4],
    ['immediately', 5], ['readability', 5],
]);

it('counts the syllables of a word', function (string $word, int $expected) {
    expect((new TextStatisticsSyllableCounter)->syllables($word))->toBe($expected);
})->with('syllables');

it('is a SyllableCounter', function () {
    expect(new TextStatisticsSyllableCounter)->toBeInstanceOf(SyllableCounter::class);
});

it('keeps the vowel under an accent, which the library would otherwise throw away', function () {
    $counter = new TextStatisticsSyllableCounter;

    expect($counter->syllables('café'))->toBe(2);
    expect($counter->syllables('jalapeño'))->toBe(4);
    expect($counter->syllables('Ångström'))->toBe(2);
});

it('reads a vowelless initialism letter by letter, and a pronounceable acronym as a word', function () {
    $counter = new TextStatisticsSyllableCounter;

    expect($counter->syllables('CDC'))->toBe(3);
    expect($counter->syllables('HTML'))->toBe(4);
    expect($counter->syllables('NASA'))->toBe(2);
    expect($counter->syllables('COVID'))->toBe(2);
});

it('counts a hyphenated word or a possessive as the word it is', function () {
    $counter = new TextStatisticsSyllableCounter;

    expect($counter->syllables('walk-ins'))->toBe(2);
    expect($counter->syllables("doctor's"))->toBe(2);
    expect($counter->syllables('COVID-19'))->toBe(2);
});

it('gives zero for a token with no letters, and never less than one for a word', function () {
    $counter = new TextStatisticsSyllableCounter;

    expect($counter->syllables('2024'))->toBe(0);
    expect($counter->syllables('—'))->toBe(0);
    expect($counter->syllables(''))->toBe(0);
    expect($counter->syllables('  '))->toBe(0);
    expect($counter->syllables('hmm'))->toBeGreaterThanOrEqual(1);
});

it('lets no deprecation notice out of the library', function () {
    $notices = [];
    set_error_handler(function (int $level, string $message) use (&$notices): bool {
        $notices[] = $message;

        return true;
    });

    try {
        (new TextStatisticsSyllableCounter)->syllables('information');
    } finally {
        restore_error_handler();
    }

    expect($notices)->toBe([]);
});

it('strips the marks off Latin letters and leaves everything else alone', function () {
    expect(Diacritics::strip('Crème brûlée, naïve café, Ångström, Œuvre, straße'))->toBe('Creme brulee, naive cafe, Angstrom, OEuvre, strasse');
    expect(Diacritics::strip('plain ascii 123 — “quotes” 日本'))->toBe('plain ascii 123 — “quotes” 日本');
});
