<?php

declare(strict_types=1);

use Bpmore\ReadabilityCore\Extraction\HtmlExtractor;
use Bpmore\ReadabilityCore\Rule\Finding;
use Bpmore\ReadabilityCore\Rule\Location;
use Bpmore\ReadabilityCore\Rule\LongSentence;
use Bpmore\ReadabilityCore\Rule\RuleSet;
use Bpmore\ReadabilityCore\Rule\Severity;
use Bpmore\ReadabilityCore\Rule\VeryLongSentence;

/** A sentence of exactly n words, starting with a capital as a sentence does. */
function sentenceOf(int $words): string
{
    return 'Word '.implode(' ', array_fill(0, $words - 1, 'word')).'.';
}

function rules(Finding ...$findings): array
{
    return array_map(fn (Finding $f) => $f->rule, $findings);
}

it('warns about a sentence over the limit, and says how long it is and what to do', function () {
    $findings = (new LongSentence)->check(paragraphs('Short. '.sentenceOf(26).' '.sentenceOf(25)));

    expect($findings)->toHaveCount(1);
    expect($findings[0]->rule)->toBe('long_sentence');
    expect($findings[0]->severity)->toBe(Severity::Warning);
    expect($findings[0]->message)->toBe('This sentence runs to 26 words. Break it in two; about 25 is as many as a reader can follow at once.');
    expect($findings[0]->location)->toEqual(new Location(0, 7, strlen(sentenceOf(26))));
    expect($findings[0]->excerpt)->toBe(sentenceOf(26));
});

it('takes its limit and severity from its options', function () {
    $rule = new LongSentence(['words' => 10, 'severity' => 'tip']);
    $findings = $rule->check(paragraphs(sentenceOf(10).' '.sentenceOf(11)));

    expect($rule->words())->toBe(10);
    expect($findings)->toHaveCount(1);
    expect($findings[0]->severity)->toBe(Severity::Tip);
    expect($findings[0]->message)->toContain('11 words')->toContain('about 10 is');
});

it('stops where the very-long rule starts, so a sentence is never said twice', function () {
    $capped = new LongSentence([], cap: 50);
    $uncapped = new LongSentence;

    expect($capped->check(paragraphs(sentenceOf(50))))->toHaveCount(1);
    expect($capped->check(paragraphs(sentenceOf(51))))->toHaveCount(0);
    expect($uncapped->check(paragraphs(sentenceOf(51))))->toHaveCount(1);
});

it('flags a very long sentence as an error, over twice the long limit by default', function () {
    $findings = (new VeryLongSentence)->check(paragraphs(sentenceOf(50).' '.sentenceOf(51)));

    expect((new VeryLongSentence)->words())->toBe(50);
    expect($findings)->toHaveCount(1);
    expect($findings[0]->rule)->toBe('very_long_sentence');
    expect($findings[0]->severity)->toBe(Severity::Error);
    expect($findings[0]->message)->toBe('This sentence runs to 51 words, far past the 50 a reader can follow. Break it into three or four.');
    expect($findings[0]->location->offset)->toBe(strlen(sentenceOf(50)) + 1);

    expect((new VeryLongSentence(['words' => 30], default: 80))->words())->toBe(30);
    expect((new VeryLongSentence([], default: 80))->words())->toBe(80);
});

it('counts each sentence of a block on its own, across sentence ends and hard breaks', function () {
    $document = (new HtmlExtractor)->extract('<p>'.sentenceOf(13).' '.sentenceOf(13).'<br>'.sentenceOf(26).'</p><ul><li>'.sentenceOf(30).'</li></ul>');
    $findings = (new LongSentence)->check($document);

    expect(array_map(fn (Finding $f) => [$f->location->block, substr_count($f->message, '26 words') + 2 * substr_count($f->message, '30 words')], $findings))
        ->toBe([[0, 1], [1, 2]]);
});

it('runs both from the config with the thresholds wired together', function () {
    // Each sentence in its own paragraph, so the paragraph rule stays quiet.
    $document = paragraphs(sentenceOf(25), sentenceOf(26), sentenceOf(50), sentenceOf(51));
    $sentenceRules = fn (RuleSet $set, $document) => rules(...$set->check($document)->ofRule('long_sentence', 'very_long_sentence')->all());

    expect($sentenceRules(RuleSet::fromConfig([]), $document))
        ->toBe(['long_sentence', 'long_sentence', 'very_long_sentence']);

    // The long limit moved to 20 and the very-long limit follows it to 40.
    $set = RuleSet::fromConfig(['long_sentence' => ['words' => 20]]);
    expect($sentenceRules($set, paragraphs(sentenceOf(21), sentenceOf(40), sentenceOf(41))))
        ->toBe(['long_sentence', 'long_sentence', 'very_long_sentence']);

    // Set on its own, the very-long limit is what it says.
    $set = RuleSet::fromConfig(['very_long_sentence' => ['words' => 30]]);
    expect($sentenceRules($set, paragraphs(sentenceOf(30), sentenceOf(31))))
        ->toBe(['long_sentence', 'very_long_sentence']);

    // Switched off, long sentences have no ceiling.
    $set = RuleSet::fromConfig(['very_long_sentence' => ['enabled' => false]]);
    expect($sentenceRules($set, paragraphs(sentenceOf(80))))->toBe(['long_sentence']);
});

it('finds nothing in a document of short sentences', function () {
    expect((new LongSentence)->check(paragraphs('One. Two three. Four five six.', sentenceOf(25))))->toBe([]);
    expect((new VeryLongSentence)->check(paragraphs(sentenceOf(50))))->toBe([]);
});
