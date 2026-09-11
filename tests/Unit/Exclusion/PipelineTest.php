<?php

declare(strict_types=1);

use Bpmore\ReadabilityCore\Exclusion\Addresses;
use Bpmore\ReadabilityCore\Exclusion\Code;
use Bpmore\ReadabilityCore\Exclusion\FiguresInTables;
use Bpmore\ReadabilityCore\Exclusion\IgnoredClasses;
use Bpmore\ReadabilityCore\Exclusion\IgnoredSets;
use Bpmore\ReadabilityCore\Exclusion\Pipeline;
use Bpmore\ReadabilityCore\Exclusion\ProperNames;
use Bpmore\ReadabilityCore\Exclusion\Quotes;
use Bpmore\ReadabilityCore\Exclusion\References;
use Bpmore\ReadabilityCore\Extraction\HtmlExtractor;

function classesOf(Pipeline $pipeline): array
{
    return array_map(static fn ($e): string => $e::class, $pipeline->exclusions());
}

it('builds every exclusion from an empty config, whole blocks first and proper names last', function () {
    expect(classesOf(Pipeline::fromConfig([])))->toBe([
        Code::class, Quotes::class, References::class, FiguresInTables::class, Addresses::class, ProperNames::class,
    ]);
});

it('builds from the config sketch in the spec, and honours a switch turned off', function () {
    $pipeline = Pipeline::fromConfig([
        'bard_sets' => ['code', 'citation'],
        'html_classes' => ['no-readability'],
        'blockquotes' => false,
        'references' => true,
        'proper_names' => false,
    ]);

    expect(classesOf($pipeline))->toBe([
        IgnoredClasses::class, IgnoredSets::class, Code::class, References::class, FiguresInTables::class, Addresses::class,
    ]);
});

it('can be taken apart by class and added to', function () {
    $pipeline = Pipeline::fromConfig([]);

    expect(classesOf($pipeline->without(ProperNames::class, Addresses::class)))->toBe([
        Code::class, Quotes::class, References::class, FiguresInTables::class,
    ]);
    expect(classesOf($pipeline->without(ProperNames::class)->with(new ProperNames)))->toBe(classesOf($pipeline));
    expect(classesOf(new Pipeline([])))->toBe([]);
});

it('gives the rules the page with its structure excluded and its names in', function () {
    $document = (new HtmlExtractor(ignoredTags: Code::INLINE_TAGS, ignoredClasses: ['no-readability']))->extract(readFixture('messy.html'));

    expect(texts(Pipeline::fromConfig(['html_classes' => ['no-readability']])->without(ProperNames::class)->apply($document)))->toBe([
        'Home Find care',
        'Flu shots & you',
        "Every year, the flu sends thousands of people to the hospital. A shot takes five minutes and it's free with most plans.\nWalk-ins welcome.",
        'Unclosed paragraphs happen in the wild.',
        'So do "smart" quotes... and CDC advice.',
        'Who should get one?',
        'Everyone six months and older',
        'Especially:',
        'People over 65',
        'Anyone who is pregnant',
        'Health care workers',
        'Our downtown clinic',
        'Text before a nested block',
        'The nested block',
        'and text after it',
        'Search',
        'Text in a custom element',
        '© 2026 Example Health',
    ]);
});

it('gives the formulas the same page with the names out as well', function () {
    $document = (new HtmlExtractor(ignoredTags: Code::INLINE_TAGS, ignoredClasses: ['no-readability']))->extract(readFixture('messy.html'));
    $texts = texts(Pipeline::fromConfig(['html_classes' => ['no-readability']])->apply($document));

    expect($texts)->toContain('So do "smart" quotes... and advice.');
    expect($texts)->toContain('care');   // "Home Find" read as a name; navigation is not prose either way
    expect($texts)->not->toContain('So do "smart" quotes... and CDC advice.');
    expect($texts)->toHaveCount(18);
});
