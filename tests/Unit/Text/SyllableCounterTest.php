<?php

declare(strict_types=1);

use Bpmore\ReadabilityCore\Text\Diacritics;
use Bpmore\ReadabilityCore\Text\EnglishSyllableCounter;
use Bpmore\ReadabilityCore\Text\SyllableCounter;

/**
 * Words with the count a dictionary gives. The rules' own heuristic
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
    expect((new EnglishSyllableCounter)->syllables($word))->toBe($expected);
})->with('syllables');

it('is a SyllableCounter', function () {
    expect(new EnglishSyllableCounter)->toBeInstanceOf(SyllableCounter::class);
});

it('keeps the vowel under an accent, which the rules would otherwise throw away', function () {
    $counter = new EnglishSyllableCounter;

    expect($counter->syllables('café'))->toBe(2);
    expect($counter->syllables('jalapeño'))->toBe(4);
    expect($counter->syllables('Ångström'))->toBe(2);
});

it('reads a vowelless initialism letter by letter, and a pronounceable acronym as a word', function () {
    $counter = new EnglishSyllableCounter;

    expect($counter->syllables('CDC'))->toBe(3);
    expect($counter->syllables('HTML'))->toBe(4);
    expect($counter->syllables('NASA'))->toBe(2);
    expect($counter->syllables('COVID'))->toBe(2);
});

it('counts a hyphenated word or a possessive as the word it is', function () {
    $counter = new EnglishSyllableCounter;

    expect($counter->syllables('walk-ins'))->toBe(2);
    expect($counter->syllables("doctor's"))->toBe(2);
    expect($counter->syllables('COVID-19'))->toBe(2);
});

it('gives zero for a token with no letters, and never less than one for a word', function () {
    $counter = new EnglishSyllableCounter;

    expect($counter->syllables('2024'))->toBe(0);
    expect($counter->syllables('—'))->toBe(0);
    expect($counter->syllables(''))->toBe(0);
    expect($counter->syllables('  '))->toBe(0);
    expect($counter->syllables('hmm'))->toBeGreaterThanOrEqual(1);
});

it('counts as the library it was ported from, on a sample of the dictionary', function () {
    // Frozen from davechild/textstatistics 1.0.3 the day it was removed,
    // after the port matched it on all 235,976 words of /usr/share/dict.
    // Some of these numbers are wrong ("science" is one here); the claim is
    // fidelity, not correctness. Improve a rule and the words it changes
    // show up here, to be checked and updated on purpose.
    $counter = new EnglishSyllableCounter;
    $differences = [];

    foreach (json_decode(readFixture('syllables.json'), true) as $word => $expected) {
        if ($counter->syllables($word) !== $expected) {
            $differences[] = $word.': '.$counter->syllables($word).' not '.$expected;
        }
    }

    expect($differences)->toBe([]);
});

it('strips the marks off Latin letters and leaves everything else alone', function () {
    expect(Diacritics::strip('Crème brûlée, naïve café, Ångström, Œuvre, straße'))->toBe('Creme brulee, naive cafe, Angstrom, OEuvre, strasse');
    expect(Diacritics::strip('plain ascii 123 — “quotes” 日本'))->toBe('plain ascii 123 — “quotes” 日本');
});
