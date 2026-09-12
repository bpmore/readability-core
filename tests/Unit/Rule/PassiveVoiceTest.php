<?php

declare(strict_types=1);

use Bpmore\ReadabilityCore\Rule\Location;
use Bpmore\ReadabilityCore\Rule\PassiveVoice;
use Bpmore\ReadabilityCore\Rule\RuleSet;
use Bpmore\ReadabilityCore\Rule\Severity;
use Bpmore\ReadabilityCore\Text\Document;

const PASSIVE = 'The results will be reviewed by your doctor.';

const ACTIVE = 'Your doctor will review the results.';

/** A document of n sentences, the first k of them passive. */
function withPassives(int $passive, int $total): Document
{
    return paragraphs(...array_merge(array_fill(0, $passive, PASSIVE), array_fill(0, $total - $passive, ACTIVE)));
}

dataset('passive sentences', [
    'will be + participle, with an agent' => [PASSIVE],
    'must be + participle' => ['The form must be signed before your visit.'],
    'is being + participle' => ['You are being treated for an infection.'],
    'have not yet been + participle' => ['Patients have not yet been told.'],
    'was + participle, a fact stated passively' => ['The clinic was founded in 1879.'],
    'is + adverb + irregular participle' => ['It is widely known that flu spreads fast.'],
    'the classic' => ['Mistakes were made.'],
    'are then + irregular participle' => ['The samples are then frozen.'],
    'is used to treat' => ['The medicine is used to treat infections.'],
]);

dataset('active sentences', [
    'the same thing, doer first' => [ACTIVE],
    'to be with a complement' => ['We are open every day.'],
    'to be with adjectives that end in -ed' => ['She is tired and interested in resting.'],
    'to be with an adverb of place' => ['The nurse was there when we arrived.'],
    'a short noun after is' => ['This is a bed.'],
    'supposed to' => ['He is supposed to call.'],
    'no to be at all' => ['Bring your card and arrive early.'],
]);

it('recognises a passive sentence', fn (string $sentence) => expect(PassiveVoice::isPassive($sentence))->toBeTrue())
    ->with('passive sentences');

it('leaves an active sentence alone', fn (string $sentence) => expect(PassiveVoice::isPassive($sentence))->toBeFalse())
    ->with('active sentences');

it('says nothing about a document under the rate, however many passive sentences it has', function () {
    expect((new PassiveVoice)->check(withPassives(2, 10)))->toBe([]);   // 20%: not over
    expect((new PassiveVoice)->check(withPassives(0, 10)))->toBe([]);
    expect((new PassiveVoice)->check(withPassives(20, 100)))->toBe([]);
});

it('makes one finding about the whole document when the rate is passed, with the sentences attached', function () {
    $findings = (new PassiveVoice)->check(withPassives(3, 10));

    expect($findings)->toHaveCount(1);
    expect($findings[0]->rule)->toBe('passive_voice');
    expect($findings[0]->severity)->toBe(Severity::Warning);
    expect($findings[0]->location)->toBeNull();
    expect($findings[0]->isAboutWholeDocument())->toBeTrue();
    expect($findings[0]->message)->toBe(
        'About 30% of the sentences here are in the passive voice: 3 of 10. Keep it under 20%. '
        .'Put the doer first where you can: "Your doctor will review the results", not "The results will be reviewed".'
    );
    expect($findings[0]->excerpt)->toBe(PASSIVE);
    expect($findings[0]->spans)->toHaveCount(3);
    expect($findings[0]->spans[0])->toEqual(new Location(0, 0, strlen(PASSIVE)));
    expect($findings[0]->spans[2]->block)->toBe(2);
});

it('stays quiet on a document too short for a rate to mean anything', function () {
    expect((new PassiveVoice)->check(withPassives(2, 4)))->toBe([]);          // 50%, but four sentences
    expect((new PassiveVoice)->check(withPassives(2, 5)))->toHaveCount(1);    // 40% of five
    expect((new PassiveVoice(['min_sentences' => 2]))->check(withPassives(2, 4)))->toHaveCount(1);
});

it('takes its rate and severity from its options', function () {
    $strict = new PassiveVoice(['max_percent' => 10, 'severity' => 'error']);
    $lax = new PassiveVoice(['max_percent' => 50]);

    expect($strict->maxPercent())->toBe(10);
    expect($strict->check(withPassives(2, 10))[0]->severity)->toBe(Severity::Error);
    expect($lax->check(withPassives(5, 10)))->toBe([]);
    expect($lax->check(withPassives(6, 10)))->toHaveCount(1);
});

it('counts a sentence once however many passives it holds, and rounds the percentage it reports', function () {
    $twice = 'The form was signed and the results were reviewed.';
    $findings = (new PassiveVoice)->check(paragraphs($twice, ACTIVE, ACTIVE, ACTIVE, ACTIVE, ACTIVE, ACTIVE));

    expect($findings)->toHaveCount(0);   // 1 of 7 is 14%

    $findings = (new PassiveVoice)->check(paragraphs($twice, $twice, ACTIVE, ACTIVE, ACTIVE, ACTIVE, ACTIVE));
    expect($findings[0]->message)->toStartWith('About 29% of the sentences here are in the passive voice: 2 of 7.');
});

it('is in the shipped set', function () {
    expect(RuleSet::fromConfig([])->handles())->toContain('passive_voice');
    expect(RuleSet::fromConfig(['passive_voice' => ['max_percent' => 5]])->check(withPassives(1, 10))->ofRule('passive_voice'))->toHaveCount(1);
});
