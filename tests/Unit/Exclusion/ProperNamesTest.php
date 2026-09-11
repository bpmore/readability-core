<?php

declare(strict_types=1);

use Bpmore\ReadabilityCore\Exclusion\ProperNames;
use Bpmore\ReadabilityCore\Extraction\HtmlExtractor;

function withoutNames(string $text): string
{
    return texts((new ProperNames)->apply(paragraphs($text)))[0] ?? '';
}

it('removes a name led by an honorific, the honorific with it, and a suffix after it', function () {
    expect(withoutNames('Dr. Smith went to St. Louis Inc. yesterday.'))->toBe('went to yesterday.');
    expect(withoutNames('I met Dr. Jane Smith, who runs the clinic (and Baptist Health).'))->toBe('I met who runs the clinic (and).');
});

it('removes a run of capitalised words in the middle of a sentence, connectors included', function () {
    expect(withoutNames('She met John Smith at the University of Arkansas for Medical Sciences.'))->toBe('She met at the.');
    expect(withoutNames('We accept Medicare, Medicaid and Blue Cross Blue Shield of Arkansas.'))->toBe('We accept.');
    expect(withoutNames('Our program is called Healthy Start; it began in 2019.'))->toBe('Our program is called; it began in 2019.');
});

it('removes acronyms and initialisms wherever they are', function () {
    expect(withoutNames('The World Health Organization and the CDC recommend a flu shot every year.'))->toBe('The and the recommend a flu shot every year.');
    expect(withoutNames('COVID-19 vaccines are safe. The U.S. Food and Drug Administration approved them in 2021.'))->toBe('vaccines are safe. The approved them in 2021.');
    expect(withoutNames('Arrive at the UAMS Medical Center by 8 a.m.'))->toBe('Arrive at the by 8 a.m.');
});

it('leaves a single capitalised word that starts a sentence, because it is probably just the first word', function () {
    expect(withoutNames('Mary left early. McDonald\'s is closed on Mondays.'))->toBe('Mary left early. McDonald\'s is closed on.');
    expect(withoutNames('Ask your doctor. Then rest. Take two tablets.'))->toBe('Ask your doctor. Then rest. Take two tablets.');
});

it('at the start of a sentence, takes a name with company but not the word the sentence starts with', function () {
    expect(withoutNames('Visit Little Rock in the fall.'))->toBe('Visit in the fall.');
    expect(withoutNames('Call Nurse Kelly or ask for Mr. O\'Brien-Jones.'))->toBe('Call or ask for.');
    expect(withoutNames('Then Mary left.'))->toBe('Then left.');
});

it('starts a new sentence at an opening quote, a colon, and a hard break', function () {
    expect(withoutNames('He said "Go home." Then Mary left. "Fine," said Tom.'))->toBe('He said "Go home." Then left. "Fine," said.');
    expect(withoutNames('Remember: Take your card.'))->toBe('Remember: Take your card.');
    expect(withoutNames("Bring your card\nArrive early"))->toBe("Bring your card\nArrive early");
});

it('leaves "I" and its contractions alone', function () {
    expect(withoutNames("I'm going, and I think I'll drive."))->toBe("I'm going, and I think I'll drive.");
});

it('leaves a title-case block alone, and headings alone, where capitals mean nothing', function () {
    foreach (['Photo ID', 'Health Care Workers', 'Flu Shots and You', 'Who should get one?', 'Call Dr. Smith'] as $text) {
        expect(withoutNames($text))->toBe($text);
    }

    // With a period it is a sentence, however short, and the name goes.
    expect(withoutNames('Call Dr. Smith.'))->toBe('Call.');

    $heading = (new HtmlExtractor)->extract('<h2>Meet Dr. Smith</h2>');
    expect(texts((new ProperNames)->apply($heading)))->toBe(['Meet Dr. Smith']);
});

it('keeps the punctuation a name wore, so the sentence still ends', function () {
    expect(withoutNames('We thank Dr. Smith.'))->toBe('We thank.');
    expect(withoutNames('Was it Dr. Smith?'))->toBe('Was it?');
});
