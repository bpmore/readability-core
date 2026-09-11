<?php

declare(strict_types=1);

use Bpmore\ReadabilityCore\Formula\Counts;

it('adds up, so a section and a document are the same kind of thing', function () {
    $sum = (new Counts(2, 20, 30, 90, 4, 5))->add(new Counts(1, 10, 12, 40, 1, 1));

    expect([$sum->sentences, $sum->words, $sum->syllables, $sum->characters, $sum->complexWords, $sum->polysyllables])
        ->toBe([3, 30, 42, 130, 5, 6]);
});

it('knows when there is nothing to grade', function () {
    expect(Counts::none()->isEmpty())->toBeTrue();
    expect((new Counts(0, 5, 5, 20, 0, 0))->isEmpty())->toBeTrue();
    expect((new Counts(1, 0, 0, 0, 0, 0))->isEmpty())->toBeTrue();
    expect((new Counts(1, 1, 1, 1, 0, 0))->isEmpty())->toBeFalse();
});

it('gives the two ratios the formulas share', function () {
    $counts = new Counts(2, 20, 30, 90, 4, 5);

    expect($counts->wordsPerSentence())->toBe(10.0);
    expect($counts->syllablesPerWord())->toBe(1.5);
});
