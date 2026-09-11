<?php

declare(strict_types=1);

use Bpmore\ReadabilityCore\Exclusion\Addresses;
use Bpmore\ReadabilityCore\Extraction\HtmlExtractor;

it('cuts an address and a phone number out of a sentence and leaves the sentence', function () {
    $document = paragraphs('Visit us at 4301 W. Markham St., Little Rock, AR 72205. Call (501) 686-7000 or 1-800-FLU-SHOT to book.');

    expect(texts((new Addresses)->apply($document)))->toBe(['Visit us at. Call or to book.']);
});

it('knows a UK street and postcode, an international number, a PO box and a Canadian postcode', function () {
    expect(texts((new Addresses)->apply(paragraphs(
        'Our office is at 12 Baker Street, London. Postcode NW1 6XE. Ring +44 20 7946 0958.',
        'Send forms to P.O. Box 250, Ottawa, ON K1A 0B1, or call 613.555.0199 ext. 42.',
    ))))->toBe([
        'Our office is at London. Postcode. Ring.',
        'Send forms to or call.',
    ]);
});

it('leaves numbers that are not addresses or phones alone', function () {
    $texts = [
        'In 2024 we gave 12,400 shots to 5 000 people over 3 days; that is 24 per hour.',
        'Take 1 tablet 3 times a day for 10 days.',
    ];

    expect(texts((new Addresses)->apply(paragraphs(...$texts))))->toBe($texts);
});

it('drops a block that was only an address, and an address element whole', function () {
    expect((new Addresses)->apply(paragraphs("4301 W. Markham St.\nSuite 200\nLittle Rock, AR 72205\n501-686-7000"))->isEmpty())->toBeTrue();

    $document = (new HtmlExtractor)->extract('<p>Find us here:</p><address>Some Clinic<br>Somewhere</address>');
    expect(texts((new Addresses)->apply($document)))->toBe(['Find us here:']);
});
