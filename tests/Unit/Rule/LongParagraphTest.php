<?php

declare(strict_types=1);

use Bpmore\ReadabilityCore\Extraction\HtmlExtractor;
use Bpmore\ReadabilityCore\Rule\Location;
use Bpmore\ReadabilityCore\Rule\LongParagraph;
use Bpmore\ReadabilityCore\Rule\RuleSet;
use Bpmore\ReadabilityCore\Rule\Severity;

it('warns about a paragraph over the word limit, pointing at the whole block', function () {
    $long = sentenceOf(151);
    $findings = (new LongParagraph)->check(paragraphs(sentenceOf(150), $long));

    expect($findings)->toHaveCount(1);
    expect($findings[0]->rule)->toBe('long_paragraph');
    expect($findings[0]->severity)->toBe(Severity::Warning);
    expect($findings[0]->message)->toBe('This paragraph runs to 151 words. Split it; one idea per paragraph, and about 150 words at most.');
    expect($findings[0]->location)->toEqual(new Location(1, 0, strlen($long)));
    expect($findings[0]->excerpt)->toBe($long);
});

it('warns about a paragraph with too many sentences, however short they are', function () {
    $findings = (new LongParagraph)->check(paragraphs(str_repeat('One two. ', 8), str_repeat('One two. ', 9)));

    expect($findings)->toHaveCount(1);
    expect($findings[0]->location->block)->toBe(1);
    expect($findings[0]->message)->toBe('This paragraph has 9 sentences. Split it; past 8 a reader loses the shape of it.');
    expect($findings[0]->excerpt)->toBe('One two.');
});

it('says both when both limits are crossed', function () {
    $findings = (new LongParagraph)->check(paragraphs(str_repeat(sentenceOf(20).' ', 9)));

    expect($findings[0]->message)->toBe('This paragraph runs to 180 words in 9 sentences. Split it; one idea per paragraph, and about 150 words at most.');
});

it('takes its limits and severity from its options', function () {
    $rule = new LongParagraph(['words' => 40, 'sentences' => 3, 'severity' => 'tip']);

    expect([$rule->words(), $rule->sentences()])->toBe([40, 3]);
    expect($rule->check(paragraphs(sentenceOf(41)))[0]->severity)->toBe(Severity::Tip);
    expect($rule->check(paragraphs('One two. Three four. Five six. Seven eight.'))[0]->message)->toContain('4 sentences')->toContain('past 3');
    expect($rule->check(paragraphs(sentenceOf(40), 'One two. Three four. Five six.')))->toBe([]);
});

it('leaves headings alone and treats every other block, a bullet included, as a paragraph', function () {
    $document = (new HtmlExtractor)->extract('<h1>'.sentenceOf(200).'</h1><ul><li>'.sentenceOf(160).'</li></ul><p>'.sentenceOf(10).'</p>');
    $findings = (new LongParagraph)->check($document);

    expect(array_map(fn ($f) => $f->location->block, $findings))->toBe([1]);
});

it('is in the shipped set', function () {
    expect(RuleSet::fromConfig([])->handles())->toContain('long_paragraph');
    expect(RuleSet::fromConfig(['long_paragraph' => ['words' => 5]])->check(paragraphs(sentenceOf(6)))->ofRule('long_paragraph'))->toHaveCount(1);
});
