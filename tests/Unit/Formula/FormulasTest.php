<?php

declare(strict_types=1);

use Bpmore\ReadabilityCore\Formula\ColemanLiau;
use Bpmore\ReadabilityCore\Formula\Counts;
use Bpmore\ReadabilityCore\Formula\FleschKincaid;
use Bpmore\ReadabilityCore\Formula\FleschReadingEase;
use Bpmore\ReadabilityCore\Formula\Grades;
use Bpmore\ReadabilityCore\Formula\GunningFog;
use Bpmore\ReadabilityCore\Formula\Smog;

// Two sentences, twenty words, thirty syllables, ninety letters, four
// complex words, five polysyllables. Every expected value below was
// worked by hand from the published formula.
const COUNTS = [2, 20, 30, 90, 4, 5];

it('computes Flesch-Kincaid: 0.39 × 10 + 11.8 × 1.5 − 15.59', function () {
    expect((new FleschKincaid)->grade(new Counts(...COUNTS)))->toEqualWithDelta(6.01, 0.0001);
});

it('computes Gunning Fog: 0.4 × (10 + 100 × 4/20)', function () {
    expect((new GunningFog)->grade(new Counts(...COUNTS)))->toEqualWithDelta(12.0, 0.0001);
});

it('computes SMOG: 1.0430 × √(5 × 30/2) + 3.1291', function () {
    expect((new Smog)->grade(new Counts(...COUNTS)))->toEqualWithDelta(1.0430 * sqrt(75) + 3.1291, 0.0001);
});

it('computes Coleman-Liau: 0.0588 × 450 − 0.296 × 10 − 15.8', function () {
    expect((new ColemanLiau)->grade(new Counts(...COUNTS)))->toEqualWithDelta(7.7, 0.0001);
});

it('computes Flesch Reading Ease: 206.835 − 1.015 × 10 − 84.6 × 1.5', function () {
    expect((new FleschReadingEase)->score(new Counts(...COUNTS)))->toEqualWithDelta(69.785, 0.0001);
});

it('refuses to grade nothing', function () {
    (new FleschKincaid)->grade(Counts::none());
})->throws(InvalidArgumentException::class, 'nothing to grade');

it('gathers every formula, named, and gives null for nothing', function () {
    $grades = Grades::of(new Counts(...COUNTS));

    expect($grades)->not->toBeNull();
    expect(array_keys($grades->byFormula()))->toBe(['Flesch-Kincaid', 'Gunning Fog', 'SMOG', 'Coleman-Liau']);
    expect($grades->byFormula()['Gunning Fog'])->toEqualWithDelta(12.0, 0.0001);
    expect($grades->readingEase)->toEqualWithDelta(69.785, 0.0001);
    expect(Grades::of(Counts::none()))->toBeNull();
});

it('names each formula for the details view', function () {
    expect((new FleschKincaid)->name())->toBe('Flesch-Kincaid');
    expect((new GunningFog)->name())->toBe('Gunning Fog');
    expect((new Smog)->name())->toBe('SMOG');
    expect((new ColemanLiau)->name())->toBe('Coleman-Liau');
});
