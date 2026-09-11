<?php

declare(strict_types=1);

use Bpmore\ReadabilityCore\Text\EnglishSegmenter;
use Bpmore\ReadabilityCore\Text\Sentence;

/**
 * The traps, with the count a reader would give. This corpus is what the
 * segmenter was chosen on: against it, PHP's ICU sentence iterator scored
 * 25 of 32 (it breaks after every honorific, having no abbreviation
 * suppressions), vanderlee/php-sentence 22 (wrong in both directions),
 * davechild/textstatistics 10 (it counts punctuation), and these rules 32.
 * A case added here that fails is a case to fix, not to delete.
 */
dataset('sentence traps', [
    'honorifics and a company suffix' => ['Dr. Smith went to St. Louis Inc. yesterday. He came back today.', 2],
    'a.m. followed by lowercase' => ['We met at 8 a.m. on Monday. It was early.', 2],
    'dotted acronym before a name' => ['The U.S. Food and Drug Administration approved it. Then we used it.', 2],
    'initials' => ['Call J. K. Rowling. She wrote it.', 2],
    'e.g.' => ["Bring your card, e.g. a driver's license. Arrive early.", 2],
    'a decimal' => ['It costs $4.50 per dose. That is cheap.', 2],
    'an ellipsis, then lowercase' => ['Wait... what? Really?!', 2],
    'a quoted sentence inside one' => ['He said "Go home." Then Mary left.', 2],
    'dialogue with attribution' => ['"Are you sure?" she asked. "Yes," he said.', 2],
    'a domain' => ['Visit example.com for details. Or call us.', 2],
    'a section number' => ['See section 2.1 for details. Then section 3.', 2],
    'several honorifics' => ['Mr. and Mrs. Jones arrived. Prof. Lee did not.', 2],
    'a colon' => ['The list: apples, pears, and plums. Buy them.', 2],
    'a bracket before the period' => ['Take one tablet daily (see the leaflet). Do not exceed the dose.', 2],
    'questions and short answers' => ['Is it safe? Yes. Is it free? Also yes.', 4],
    'p.m. that ends a sentence' => ['The meeting is at 3 p.m. Please be on time.', 2],
    'D.C. that ends a sentence' => ['I live in Washington, D.C. It is nice.', 2],
    'an acronym without periods' => ['Read the FAQ. It helps.', 2],
    'no punctuation' => ['No punctuation at all', 1],
    'three short ones' => ['One. Two. Three.', 3],
    'Corp. followed by lowercase' => ['He works for Acme Corp. and lives nearby.', 1],
    'degrees' => ['She has a Ph.D. in biology. He has an M.D.', 2],
    'a www domain' => ['Go to www.example.org. Then sign in.', 2],
    'doubled exclamation marks' => ['This is it!! Really!!!', 2],
    'a closing quote at the end' => ['It ends with a quote."', 1],
    'a unit before a capital' => ['Give 0.5 ml. Repeat in 4 hrs.', 2],
    'an abbreviation before a number' => ['Approx. 20 people came. Most left early.', 2],
    'an abbreviation in brackets' => ['Contact Dr. Lee (ext. 42). He will help.', 2],
    'a bracketed year' => ['The vaccine (approved in 2021) is free. Ask us.', 2],
    'etc. that ends a sentence' => ['Symptoms include fever, cough, etc. See a doctor if they last.', 2],
    'a name with initials and a degree' => ['Your appointment is with Dr. J. Smith, M.D. Bring your card.', 2],
    'days of the week' => ['Open Mon. to Fri. from 9 to 5. Closed weekends.', 2],
]);

it('counts the sentences a reader would', function (string $text, int $expected) {
    $sentences = (new EnglishSegmenter)->sentences($text);

    expect($sentences)->toHaveCount($expected, implode(' | ', array_map(fn (Sentence $s) => $s->text, $sentences)));
})->with('sentence traps');

it('returns the sentences themselves, trimmed, with where each starts', function () {
    $sentences = (new EnglishSegmenter)->sentences('  Leading space. Then "quoted." Done!  ');

    expect(array_map(fn (Sentence $s) => [$s->text, $s->offset], $sentences))->toBe([
        ['Leading space.', 2],
        ['Then "quoted."', 17],
        ['Done!', 32],
    ]);
});

it('ends a sentence at a hard break, whatever punctuation is missing', function () {
    $sentences = (new EnglishSegmenter)->sentences("Call us today\nMonday to Friday. Thanks.");

    expect(array_map(fn (Sentence $s) => [$s->text, $s->offset], $sentences))->toBe([
        ['Call us today', 0],
        ['Monday to Friday.', 14],
        ['Thanks.', 32],
    ]);
});

it('returns nothing for text with no words in it', function () {
    expect((new EnglishSegmenter)->sentences(''))->toBe([]);
    expect((new EnglishSegmenter)->sentences('...'))->toBe([]);
    expect((new EnglishSegmenter)->sentences("— \n !?"))->toBe([]);
});

it('reads multibyte text without losing its place', function () {
    $sentences = (new EnglishSegmenter)->sentences('Café au lait. Crème brûlée! Ça va? ça va.');

    expect(array_map(fn (Sentence $s) => $s->text, $sentences))->toBe(['Café au lait.', 'Crème brûlée!', 'Ça va? ça va.']);
    expect($sentences[1]->offset)->toBe(strlen('Café au lait. '));
});
