<?php

declare(strict_types=1);

use Bpmore\ReadabilityCore\Extraction\HtmlExtractor;
use Bpmore\ReadabilityCore\Rule\Location;
use Bpmore\ReadabilityCore\Rule\RuleSet;
use Bpmore\ReadabilityCore\Rule\Severity;
use Bpmore\ReadabilityCore\Rule\UnexpandedAcronym;

function acronyms(string ...$texts): array
{
    return array_map(fn ($f) => $f->excerpt, (new UnexpandedAcronym)->check(paragraphs(...$texts)));
}

it('flags an acronym the page never spells out, once, at its first use', function () {
    $findings = (new UnexpandedAcronym)->check(paragraphs('Ask the NHS. The NHS will know.', 'The NHS again.'));

    expect($findings)->toHaveCount(1);
    expect($findings[0]->rule)->toBe('unexpanded_acronym');
    expect($findings[0]->severity)->toBe(Severity::Warning);
    expect($findings[0]->location)->toEqual(new Location(0, 8, 3));
    expect($findings[0]->excerpt)->toBe('NHS');
    expect($findings[0]->message)->toBe('"NHS" is never spelled out. Write it in full the first time, with the short form after it in brackets, and use the short form from then on.');
});

it('accepts the convention: spelled out, then the short form in brackets', function () {
    expect(acronyms('The Centers for Disease Control and Prevention (CDC) advise a flu shot. The CDC also advises rest.'))->toBe([]);
    expect(acronyms('Magnetic resonance imaging (MRI) is painless. An MRI takes an hour.'))->toBe([]);
});

it('accepts the other way round: the short form, then spelled out in brackets', function () {
    expect(acronyms('An MRI (magnetic resonance imaging) scan is painless.'))->toBe([]);
});

it('accepts a phrase whose initials spell it, small words skipped, in either case', function () {
    expect(acronyms('The World Health Organization agrees; WHO guidance is online.'))->toBe([]);
    expect(acronyms('Your emergency department is open. The ED never closes.'))->toBe([]);
    expect(acronyms('The Centers for Disease Control and Prevention advise it, and the CDC is clear.'))->toBe([]);
});

it('says so when the spelling-out comes after the first use', function () {
    $findings = (new UnexpandedAcronym)->check(paragraphs('Ask about an MRI.', 'An MRI (magnetic resonance imaging) is painless.'));

    expect($findings)->toHaveCount(1);
    expect($findings[0]->location->block)->toBe(0);
    expect($findings[0]->message)->toBe('"MRI" is spelled out later than this, its first use. Spell it out here, the first time, and use the short form after that.');
});

it('folds plurals, possessives and periods into one acronym', function () {
    expect(acronyms('Two MRIs, and the MRI\'s cost. The M.R.I. again.'))->toBe(['MRIs']);
    expect(acronyms('The CDC\'s advice is clear.'))->toBe(["CDC's"]);
});

it('leaves the universally known alone, and what a site adds to ignore', function () {
    expect(acronyms('Your GP, the ER, a PDF, the U.S. FDA and HIV, at 9 AM.'))->toBe(['FDA']);
    expect(array_map(fn ($f) => $f->excerpt, (new UnexpandedAcronym(['ignore' => ['FDA']]))->check(paragraphs('The U.S. FDA and the NHS.'))))->toBe(['NHS']);
});

it('does not take a Roman numeral, a single capital, or a mixed-case word for an acronym', function () {
    expect(acronyms('Chapter IV covers it, and Part III, then XIV and MCMXC. Vitamin A helps. Ask about your HbA1c.'))->toBe([]);

    // Made of numeral letters, but not numerals.
    expect(UnexpandedAcronym::isAcronym('CDC'))->toBeTrue();
    expect(UnexpandedAcronym::isAcronym('DVD'))->toBeTrue();
    expect(UnexpandedAcronym::isAcronym('IV'))->toBeFalse();
    expect(UnexpandedAcronym::isAcronym('A'))->toBeFalse();
});

it('does not scan headings, so an acronym in a title that the first paragraph defines is fine', function () {
    $document = (new HtmlExtractor)->extract('<h1>About the CDC</h1><p>The Centers for Disease Control and Prevention (CDC) advise it.</p><h2>MRI</h2>');

    expect((new UnexpandedAcronym)->check($document))->toBe([]);
});

it('is in the shipped set', function () {
    expect(RuleSet::fromConfig([])->handles())->toContain('unexpanded_acronym');
    expect(RuleSet::fromConfig([])->check(paragraphs('Ask the NHS.'))->ofRule('unexpanded_acronym'))->toHaveCount(1);
});
