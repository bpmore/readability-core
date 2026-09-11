<?php

declare(strict_types=1);

use Bpmore\ReadabilityCore\Extraction\HtmlExtractor;
use Bpmore\ReadabilityCore\Formula\Tally;
use Bpmore\ReadabilityCore\Text\EnglishSegmenter;
use Bpmore\ReadabilityCore\Text\EnglishSyllableCounter;
use Bpmore\ReadabilityCore\Text\SyllableCounter;

/**
 * A counter that answers from a table, so the tally's arithmetic is
 * tested apart from the syllable rules. Anything not listed is one.
 */
function tableCounter(array $table): SyllableCounter
{
    return new class($table) implements SyllableCounter
    {
        public function __construct(private array $table) {}

        public function syllables(string $word): int
        {
            return $this->table[strtolower($word)] ?? (preg_match('/\p{L}/u', $word) === 1 ? 1 : 0);
        }
    };
}

it('tallies sentences, words, syllables and characters', function () {
    $tally = new Tally(new EnglishSegmenter, tableCounter(['minutes' => 2, 'today' => 2]));

    $counts = $tally->ofText('It takes 5 minutes. Ask us today!');

    expect([$counts->sentences, $counts->words, $counts->syllables, $counts->characters])->toBe([2, 7, 9, 25]);
});

it('counts a number as a one-syllable word', function () {
    $counts = (new Tally(new EnglishSegmenter, tableCounter([])))->ofText('In 2021 we gave 12,400 shots.');

    expect($counts->words)->toBe(6);
    expect($counts->syllables)->toBe(6);
});

it('counts polysyllables plainly and complex words the way Gunning did', function () {
    $table = ['hospital' => 3, 'attended' => 3, 'attend' => 2, 'well-being' => 3, 'beginning' => 3, 'begin' => 2, 'medicines' => 3, 'medicin' => 3, 'immediately' => 5];
    $tally = new Tally(new EnglishSegmenter, tableCounter($table));

    // attended, beginning: three syllables only because of the ending; not complex.
    // well-being: a compound; not complex. medicines: still three without the -es; complex.
    $counts = $tally->ofText('The hospital attended to well-being from the beginning, with medicines given immediately.');

    expect($counts->polysyllables)->toBe(6);
    expect($counts->complexWords)->toBe(3);
});

it('leaves headings out and counts every other block', function () {
    $document = (new HtmlExtractor)->extract('<h1>Flu shots</h1><p>Get one. It is free.</p><ul><li>Photo ID</li></ul><h2>When?</h2><p>Now.</p>');
    $counts = (new Tally(new EnglishSegmenter, new EnglishSyllableCounter))->of($document);

    expect($counts->sentences)->toBe(4);   // two in the paragraph, the list item, "Now."
    expect($counts->words)->toBe(8);
});

it('tallies an empty document as nothing', function () {
    $counts = (new Tally(new EnglishSegmenter, new EnglishSyllableCounter))->of((new HtmlExtractor)->extract('<h1>Only a heading</h1>'));

    expect($counts->isEmpty())->toBeTrue();
});
