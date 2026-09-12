<?php

declare(strict_types=1);

use Bpmore\ReadabilityCore\Exclusion\ProperNames;
use Bpmore\ReadabilityCore\Extraction\BardExtractor;
use Bpmore\ReadabilityCore\Extraction\HtmlExtractor;
use Bpmore\ReadabilityCore\Formula\Grades;
use Bpmore\ReadabilityCore\Formula\Section;
use Bpmore\ReadabilityCore\Formula\Sections;
use Bpmore\ReadabilityCore\Formula\Tally;
use Bpmore\ReadabilityCore\Rule\RuleSet;
use Bpmore\ReadabilityCore\Text\Document;
use Bpmore\ReadabilityCore\Text\EnglishSegmenter;
use Bpmore\ReadabilityCore\Text\EnglishSyllableCounter;

function sections(Document $document, bool $namesOut = false): array
{
    return Sections::of($document, new Tally(new EnglishSegmenter, new EnglishSyllableCounter), $namesOut ? new ProperNames : null);
}

function outlineOf(array $sections): array
{
    return array_map(fn (Section $s) => [$s->level(), $s->title(), $s->indexes], $sections);
}

const PAGE = '<p>A flu shot keeps you well. It is free.</p>'
    .'<h2>Before your visit</h2><p>Bring your card. Arrive early. Ask us anything.</p>'
    .'<h3>What to bring</h3><ul><li>Photo ID</li><li>Your insurance card</li></ul>'
    .'<h2>The fine print</h2><p>Immunization against seasonal influenza substantially reduces hospitalization among immunocompromised populations, according to epidemiological surveillance conducted by the Centers for Disease Control and Prevention.</p>'
    .'<h2>Empty</h2>';

it('cuts a document at every heading, the lead first, each section carrying its blocks by index', function () {
    $sections = sections((new HtmlExtractor)->extract(PAGE));

    expect(outlineOf($sections))->toBe([
        [0, '', [0]],
        [2, 'Before your visit', [1, 2]],
        [3, 'What to bring', [3, 4, 5]],
        [2, 'The fine print', [6, 7]],
        [2, 'Empty', [8]],
    ]);
    expect($sections[0]->isLead())->toBeTrue();
    expect($sections[1]->isLead())->toBeFalse();
    expect($sections[1]->heading->text)->toBe('Before your visit');
    expect(array_map(fn ($b) => $b->text, $sections[2]->blocks))->toBe(['What to bring', 'Photo ID', 'Your insurance card']);
    expect($sections[1]->contains(2))->toBeTrue();
    expect($sections[1]->contains(3))->toBeFalse();
});

it('surfaces the section that is far harder than the page it sits in', function () {
    $document = (new HtmlExtractor)->extract(PAGE);
    $sections = sections($document, namesOut: true);
    $whole = Grades::of((new Tally(new EnglishSegmenter, new EnglishSyllableCounter))->of((new ProperNames)->apply($document)));

    expect($whole->band()->low)->toBeBetween(10, 14);
    expect($sections[1]->band()->high)->toBeLessThanOrEqual(4);
    expect($sections[3]->band()->label())->toBe('Grade 17+');
    expect(array_map(fn (Section $s) => $s->title(), Sections::hardestFirst($sections))[0])->toBe('The fine print');
});

it('keeps a heading with nothing under it as a section with nothing to grade', function () {
    $sections = sections((new HtmlExtractor)->extract(PAGE));

    expect($sections[4]->isEmpty())->toBeTrue();
    expect($sections[4]->grades())->toBeNull();
    expect($sections[4]->band())->toBeNull();
    expect(Sections::hardestFirst($sections))->toHaveCount(4);
});

it('has no lead section when the document starts with a heading, and one section when it has no headings', function () {
    expect(outlineOf(sections((new HtmlExtractor)->extract('<h1>Title</h1><p>Body.</p>'))))->toBe([[1, 'Title', [0, 1]]]);
    expect(outlineOf(sections((new HtmlExtractor)->extract('<p>One.</p><p>Two.</p>'))))->toBe([[0, '', [0, 1]]]);
    expect(sections(Document::empty()))->toBe([]);
});

it('chunks a Bard tree exactly as it chunks HTML, because both are the same blocks', function () {
    $bard = (new BardExtractor)->extract(readFixture('bard.json'));
    $sections = sections($bard);

    expect(outlineOf($sections))->toBe([[2, 'Before your visit', range(0, 11)]]);
    expect($sections[0]->counts->words)->toBeGreaterThan(30);

    $html = (new HtmlExtractor)->extract('<h2>Before your visit</h2><p>Bring your insurance card and a list of medicines you take.</p>');
    expect(outlineOf(sections($html)))->toBe([[2, 'Before your visit', [0, 1]]]);
});

it('takes names out before grading a section, without moving any block index', function () {
    $document = (new HtmlExtractor)->extract('<h2>Who</h2><p>Mary Smith.</p><p>We met Dr. Jane Smith at Baptist Health today.</p>');

    $namesIn = sections($document);
    $namesOut = sections($document, namesOut: true);

    expect($namesOut[0]->indexes)->toBe($namesIn[0]->indexes)->toBe([0, 1, 2]);
    expect($namesOut[0]->counts->words)->toBeLessThan($namesIn[0]->counts->words);
    expect($namesOut[0]->counts->words)->toBe(3);   // "We met today." — "at" joins the name as a connector
});

it('picks its own findings out of the document\'s, leaving whole-document findings to nobody', function () {
    $long = 'Word '.implode(' ', array_fill(0, 30, 'word')).'.';
    $document = (new HtmlExtractor)->extract('<h2>One</h2><p>'.$long.'</p><h2>Two</h2><p>Short and plain.</p>');
    $findings = RuleSet::fromConfig(['passive_voice' => ['min_sentences' => 1]])->check($document);
    $sections = sections($document);

    expect($sections[0]->findings($findings)->ofRule('long_sentence'))->toHaveCount(1);
    expect($sections[1]->findings($findings))->toHaveCount(0);
    expect($findings->inBlocks(99))->toHaveCount(0);
});
