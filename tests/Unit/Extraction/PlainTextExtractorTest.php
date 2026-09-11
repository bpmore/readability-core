<?php

declare(strict_types=1);

use Bpmore\ReadabilityCore\Extraction\PlainTextExtractor;

it('splits paragraphs on blank lines and keeps a single newline as a hard break', function () {
    $document = (new PlainTextExtractor)->extract("First paragraph\nstill first.\n\nSecond paragraph.\n   \n\n\nThird.");

    expect(outline($document))->toBe([
        ['paragraph', "First paragraph\nstill first."],
        ['paragraph', 'Second paragraph.'],
        ['paragraph', 'Third.'],
    ]);
    expect($document->blocks[0]->origin->tag)->toBe('p');
    expect($document->blocks[0]->origin->ancestors)->toBe([]);
});

it('reads Windows and old Mac line endings as line endings', function () {
    expect(outline((new PlainTextExtractor)->extract("One\r\n\r\nTwo\r\rThree")))->toBe([
        ['paragraph', 'One'], ['paragraph', 'Two'], ['paragraph', 'Three'],
    ]);
});

it('reads a single-line field as one paragraph, and nothing as nothing', function () {
    expect(outline((new PlainTextExtractor)->extract('A short title')))->toBe([['paragraph', 'A short title']]);
    expect((new PlainTextExtractor)->extract('')->isEmpty())->toBeTrue();
    expect((new PlainTextExtractor)->extract("\n\n  \n")->isEmpty())->toBeTrue();
});

it('does not decode entities, because in plain text an ampersand-amp is what the author typed', function () {
    expect((new PlainTextExtractor)->extract('Fish &amp; chips <b>bold</b>')->text())->toBe('Fish &amp; chips <b>bold</b>');
});

it('refuses an array', function () {
    (new PlainTextExtractor)->extract(['not', 'text']);
})->throws(InvalidArgumentException::class);
