<?php

declare(strict_types=1);

use Bpmore\ReadabilityCore\Rule\Dictionary;
use Bpmore\ReadabilityCore\Rule\DictionaryEntry;
use Bpmore\ReadabilityCore\Rule\Jargon;
use Bpmore\ReadabilityCore\Rule\Location;
use Bpmore\ReadabilityCore\Rule\RuleSet;
use Bpmore\ReadabilityCore\Rule\Severity;

function jargon(array $terms, string ...$texts): array
{
    return (new Jargon([], Dictionary::fromArray($terms)))->check(paragraphs(...$texts));
}

it('reads a dictionary from an array in every shape a site file takes', function () {
    $dictionary = Dictionary::fromArray([
        'utilize' => 'use',
        'in order to' => ['to'],
        'please be advised that' => [],
        'ASAP' => null,
        'HbA1c' => ['replacements' => ['A1C', 'blood sugar test'], 'note' => 'Patients know A1C.'],
        '  ' => 'ignored',
    ]);

    expect($dictionary)->toHaveCount(5);
    expect($dictionary->get('UTILIZE'))->toEqual(new DictionaryEntry('utilize', ['use']));
    expect($dictionary->get('in order to')->replacements)->toBe(['to']);
    expect($dictionary->get('please be advised that')->isBanned())->toBeTrue();
    expect($dictionary->get('asap')->isBanned())->toBeTrue();
    expect($dictionary->get('hba1c'))->toEqual(new DictionaryEntry('HbA1c', ['A1C', 'blood sugar test'], 'Patients know A1C.'));
    expect($dictionary->get('hba1c')->suggestion())->toBe('A1C');
    expect($dictionary->has('nothing'))->toBeFalse();
});

it('ships a starter of plain-language substitutions, every entry a term with what to say instead', function () {
    $starter = Dictionary::starter();

    expect(count($starter))->toBeGreaterThan(150);
    expect($starter->get('utilize')->replacements)->toBe(['use']);
    expect($starter->get('in order to')->replacements)->toBe(['to']);
    expect($starter->get('please be advised that')->isBanned())->toBeTrue();

    foreach ($starter as $key => $entry) {
        expect($key)->toBe(mb_strtolower($entry->term));
        expect($entry->term)->not->toStartWith('_');
    }
});

it('lays a site dictionary over the starter, the site winning, and comes back out for export', function () {
    $site = Dictionary::fromArray(['utilize' => ['employ'], 'HbA1c' => ['replacements' => ['A1C'], 'note' => 'Say A1C.']]);
    $merged = Dictionary::starter()->merge($site);

    expect(count($merged))->toBe(count(Dictionary::starter()) + 1);
    expect($merged->get('utilize')->replacements)->toBe(['employ']);
    expect($merged->without('utilize', 'HBA1C')->has('utilize'))->toBeFalse();

    expect($site->toArray())->toBe(['utilize' => ['employ'], 'HbA1c' => ['replacements' => ['A1C'], 'note' => 'Say A1C.']]);
    expect(Dictionary::fromArray($site->toArray())->toArray())->toBe($site->toArray());
    expect(Dictionary::empty())->toHaveCount(0);
});

it('flags each occurrence of a term, any case, whole words only, with what to say instead', function () {
    $findings = jargon(['utilize' => 'use'], 'We utilize forms. Utilize them! No utilizer here.');

    expect($findings)->toHaveCount(2);
    expect($findings[0]->rule)->toBe('jargon');
    expect($findings[0]->severity)->toBe(Severity::Warning);
    expect($findings[0]->message)->toBe('Say "use" instead of "utilize".');
    expect($findings[0]->location)->toEqual(new Location(0, 3, 7));
    expect($findings[0]->excerpt)->toBe('utilize');
    expect($findings[0]->suggestion)->toBe('use');
    expect($findings[1]->excerpt)->toBe('Utilize');
});

it('catches the inflections of a single-word term, and a phrase across a hard break', function () {
    expect(array_map(fn ($f) => $f->excerpt, jargon(['utilize' => 'use', 'notify' => 'tell', 'assist' => 'help'],
        'Utilizing it, they utilized, and it utilizes. Notified, notifies, notifying. Assisted, assisting, assistance.')))
        ->toBe(['Utilizing', 'utilized', 'utilizes', 'Notified', 'notifies', 'notifying', 'Assisted', 'assisting']);

    expect(jargon(['in order to' => 'to'], "We do this in order\nto help.")[0]->excerpt)->toBe("in order\nto");
});

it('lets the longer of two overlapping terms speak, and not the shorter inside it', function () {
    $findings = jargon(['in order to' => 'to', 'order' => 'ask for'], 'Do it in order to finish. Then order lunch.');

    expect(array_map(fn ($f) => $f->excerpt, $findings))->toBe(['in order to', 'order']);
});

it('says a banned term is to be avoided, and adds the entry\'s note when there is one', function () {
    $findings = jargon([
        'please be advised that' => [],
        'HbA1c' => ['replacements' => ['A1C', 'blood sugar test'], 'note' => 'Patients know A1C.'],
    ], 'Please be advised that your HbA1c is due.');

    expect($findings[0]->message)->toBe('"Please be advised that" is on the site\'s list of words to avoid. Say it another way.');
    expect($findings[0]->suggestion)->toBeNull();
    expect($findings[1]->message)->toBe('Say "A1C" or "blood sugar test" instead of "HbA1c". Patients know A1C.');
    expect($findings[1]->suggestion)->toBe('A1C');
});

it('returns findings in reading order whatever the dictionary\'s order', function () {
    $findings = jargon(['prior to' => 'before', 'utilize' => 'use'], 'We utilize it prior to noon.', 'Utilize it.');

    expect(array_map(fn ($f) => [$f->location->block, $f->location->offset], $findings))->toBe([[0, 3], [0, 14], [1, 0]]);
});

it('takes its dictionary from the option: the starter by default, an array, a Dictionary, or none', function () {
    expect((new Jargon)->dictionary()->has('utilize'))->toBeTrue();
    expect((new Jargon(['dictionary' => 'default']))->dictionary()->has('utilize'))->toBeTrue();
    expect((new Jargon(['dictionary' => ['foo' => 'bar']]))->dictionary()->has('foo'))->toBeTrue();
    expect((new Jargon(['dictionary' => Dictionary::fromArray(['x' => 'y'])]))->dictionary()->has('x'))->toBeTrue();
    expect((new Jargon(['dictionary' => 'none']))->dictionary())->toHaveCount(0);
    expect((new Jargon(['dictionary' => 'default'], Dictionary::empty()))->dictionary())->toHaveCount(0);   // given directly wins

    expect(fn () => new Jargon(['dictionary' => 'clinical']))->toThrow(InvalidArgumentException::class, 'cannot load a dictionary called "clinical"');
});

it('is in the shipped set, on the starter', function () {
    expect(RuleSet::fromConfig([])->handles())->toContain('jargon');
    expect(RuleSet::fromConfig([])->check(paragraphs('We utilize forms in order to help.'))->ofRule('jargon'))->toHaveCount(2);
});
