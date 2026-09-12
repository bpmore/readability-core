<?php

declare(strict_types=1);

use Bpmore\ReadabilityCore\Rule\Dictionary;
use Bpmore\ReadabilityCore\Rule\Filler;
use Bpmore\ReadabilityCore\Rule\Location;
use Bpmore\ReadabilityCore\Rule\Nominalization;
use Bpmore\ReadabilityCore\Rule\RuleSet;
use Bpmore\ReadabilityCore\Rule\Severity;

function excerpts(array $findings): array
{
    return array_map(fn ($f) => $f->excerpt, $findings);
}

// ---- filler and hedging -----------------------------------------------------

it('tips a filler word or phrase to cut, and a hedge to say plainly', function () {
    $findings = (new Filler)->check(paragraphs('Basically, it is important to note that we are open. Perhaps it tends to rain.'));

    expect(excerpts($findings))->toBe(['Basically', 'it is important to note that', 'Perhaps', 'tends to']);
    expect($findings[0]->rule)->toBe('filler');
    expect($findings[0]->severity)->toBe(Severity::Tip);
    expect($findings[0]->message)->toBe('"Basically" adds nothing. Cut it.');
    expect($findings[0]->location)->toEqual(new Location(0, 0, 9));
    expect($findings[2]->message)->toBe('"Perhaps" hedges. Say it plainly, or say how likely it is.');
    expect($findings[3]->message)->toBe('"tends to" hedges. Say it plainly, or say how likely it is.');
});

it('matches whole words only, in any case, and the longest phrase where two overlap', function () {
    expect(excerpts((new Filler)->check(paragraphs('Justin just left. Please note that it is important to note that.'))))
        ->toBe(['just', 'Please note that', 'it is important to note that']);
});

it('lets a site allow a word it uses on purpose', function () {
    expect(excerpts((new Filler(['ignore' => ['very', 'Perhaps']]))->check(paragraphs('Very good, perhaps. Really.'))))->toBe(['Really']);
});

it('shares nothing with the jargon starter, so no words get a warning and a tip at once', function () {
    $starter = array_keys(iterator_to_array(Dictionary::starter()));
    $tips = array_map(strtolower(...), [...Filler::FILLER, ...Filler::HEDGES]);

    expect(array_values(array_intersect($tips, $starter)))->toBe([]);
    expect(array_values(array_intersect(Filler::FILLER, Filler::HEDGES)))->toBe([]);
});

// ---- nominalizations --------------------------------------------------------

it('tips a verb hiding behind a light verb, and says the verb', function () {
    $findings = (new Nominalization)->check(paragraphs('We will make a decision soon and conduct a thorough investigation. They gave consideration to it and made an announcement.'));

    expect(excerpts($findings))->toBe(['make a decision', 'conduct a thorough investigation', 'gave consideration', 'made an announcement']);
    expect($findings[0]->rule)->toBe('nominalization');
    expect($findings[0]->severity)->toBe(Severity::Tip);
    expect($findings[0]->message)->toBe('"make a decision" hides the verb. Say "decide".');
    expect($findings[0]->suggestion)->toBe('decide');
    expect($findings[0]->location)->toEqual(new Location(0, 8, 15));
    expect($findings[1]->suggestion)->toBe('investigate');
});

it('tips a verb hiding in an of-construction', function () {
    $findings = (new Nominalization)->check(paragraphs('The implementation of the plan begins after our assessment of the risks.'));

    expect(excerpts($findings))->toBe(['The implementation of', 'our assessment of']);
    expect($findings[0]->message)->toBe('"The implementation of" hides the verb. Say "implement".');
    expect($findings[1]->suggestion)->toBe('assess');
});

it('leaves a noun on its own alone, and the phrases a health site lives on', function () {
    $texts = [
        'Your treatment is free and the decision is yours.',
        'Make an appointment with the department of health.',
        'The goal of this page is clear. Do you have insurance?',
        'Seek treatment right away. You require a referral.',
        'Fill in the application and bring your prescription.',
    ];

    expect((new Nominalization)->check(paragraphs(...$texts)))->toBe([]);
});

it('takes its severity from the options, as any rule does', function () {
    expect((new Nominalization(['severity' => 'warning']))->check(paragraphs('We made a decision.'))[0]->severity)->toBe(Severity::Warning);
    expect((new Filler(['severity' => 'error']))->check(paragraphs('Basically no.'))[0]->severity)->toBe(Severity::Error);
});

it('is in the shipped set', function () {
    expect(RuleSet::fromConfig([])->handles())->toContain('nominalization', 'filler');

    $findings = RuleSet::fromConfig([])->check(paragraphs('Basically we will make a decision.'));
    expect($findings->ofRule('filler'))->toHaveCount(1);
    expect($findings->ofRule('nominalization'))->toHaveCount(1);
});
