<?php

declare(strict_types=1);

use Bpmore\ReadabilityCore\Formula\ColemanLiau;
use Bpmore\ReadabilityCore\Formula\Counts;
use Bpmore\ReadabilityCore\Formula\FleschKincaid;
use Bpmore\ReadabilityCore\Formula\FleschReadingEase;
use Bpmore\ReadabilityCore\Formula\Tally;
use Bpmore\ReadabilityCore\Text\EnglishSegmenter;
use Bpmore\ReadabilityCore\Text\EnglishSyllableCounter;

/**
 * Texts whose scores somebody else published, run through the whole
 * counting chain: tokenizer, segmenter, syllable counter, formula. The
 * sources are the Wikipedia articles on each formula, which carry worked
 * examples with their counts. No worked passage for SMOG or Gunning Fog
 * exists in an accessible source; those two are covered by the
 * hand-computed formula tests and by the counts these texts share.
 *
 * Nothing here goes through the exclusions: the published counts include
 * the names, and the claim under test is that we count and compute as
 * the formula's authors did.
 */
function tally(): Tally
{
    return new Tally(new EnglishSegmenter, new EnglishSyllableCounter);
}

it('scores "The cat sat on the mat." at 116, as Flesch\'s article does', function () {
    $counts = tally()->ofText('The cat sat on the mat.');

    expect([$counts->sentences, $counts->words, $counts->syllables])->toBe([1, 6, 6]);
    expect((new FleschReadingEase)->score($counts))->toEqualWithDelta(116, 0.5);
});

it('scores the "reading passage unto itself" sentence at 69, as published', function () {
    $counts = tally()->ofText('This sentence, taken as a reading passage unto itself, is being used to prove a point.');

    // 23 syllables: "used" and "prove" have one each. The counter as it
    // was ported gave them two, and this sentence is how that was found.
    expect([$counts->sentences, $counts->words, $counts->syllables])->toBe([1, 16, 23]);
    expect((new FleschReadingEase)->score($counts))->toEqualWithDelta(69, 0.5);
});

it('scores the Australian platypus sentence within the ambiguity of two of its words', function () {
    $text = 'The Australian platypus is seemingly a hybrid of a mammal and reptilian creature.';
    $counts = tally()->ofText($text);

    // Published: 13 words, 24 syllables, reading ease 37.5, grade 11.3.
    // We count 26 syllables: "Australian" and "reptilian" are four here and
    // three there, and Merriam-Webster allows either. So the counts that
    // are not in doubt are exact, the scores are within what two syllables
    // in thirteen words move them, and the formulas themselves are shown
    // to give the published numbers from the published syllable count.
    expect([$counts->sentences, $counts->words])->toBe([1, 13]);
    expect($counts->syllables)->toBeBetween(24, 26);
    expect((new FleschKincaid)->grade($counts))->toEqualWithDelta(11.3, 2.0);
    expect((new FleschReadingEase)->score($counts))->toEqualWithDelta(37.5, 14.0);

    $published = new Counts($counts->sentences, $counts->words, 24, $counts->characters, $counts->complexWords, $counts->polysyllables);
    expect((new FleschKincaid)->grade($published))->toEqualWithDelta(11.3, 0.05);
    expect((new FleschReadingEase)->score($published))->toEqualWithDelta(37.5, 0.05);
});

it('scores the abstract of Coleman and Liau\'s own paper at 14.5, as their article does', function () {
    $abstract = 'Existing computer programs that measure readability are based largely upon subroutines which estimate '
        .'number of syllables, usually by counting vowels. The shortcoming in estimating syllables is that it '
        .'necessitates keypunching the prose into the computer. There is no need to estimate syllables since word '
        .'length in letters is a better predictor of readability than word length in syllables. Therefore, a new '
        .'readability formula was computed that has for its predictors letters per 100 words and sentences per 100 '
        .'words. Both predictors can be counted by an optical scanning device, and thus the formula makes it '
        .'economically feasible for an organization such as the U.S. Office of Education to calibrate the '
        .'readability of all textbooks for the public school system.';

    $counts = tally()->ofText($abstract);

    // Five sentences, one of them past "U.S. Office"; 119 words; 639 letters and digits.
    expect([$counts->sentences, $counts->words, $counts->characters])->toBe([5, 119, 639]);
    expect((new ColemanLiau)->grade($counts))->toEqualWithDelta(14.5, 0.05);
});

it('gives Green Eggs and Ham its published grade of −1.3 from its published ratios', function () {
    // 5.7 words per sentence and 1.02 syllables per word, per the article.
    // The book itself is not here; the ratios are.
    $counts = new Counts(1000, 5700, 5814, 0, 0, 0);

    expect((new FleschKincaid)->grade($counts))->toEqualWithDelta(-1.3, 0.05);
});
