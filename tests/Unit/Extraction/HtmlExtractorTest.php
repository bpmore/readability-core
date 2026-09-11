<?php

declare(strict_types=1);

use Bpmore\ReadabilityCore\Extraction\HtmlExtractor;
use Bpmore\ReadabilityCore\Text\BlockKind;

it('reads a whole messy page as the blocks a reader would read, in order', function () {
    $document = (new HtmlExtractor)->extract(readFixture('messy.html'));

    expect(outline($document))->toBe([
        ['other', 'Home Find care'],
        ['heading', 'Flu shots & you'],
        ['paragraph', "Every year, the flu sends thousands of people to the hospital. A shot takes five minutes and it's free with most plans.\nWalk-ins welcome."],
        ['paragraph', 'Unclosed paragraphs happen in the wild.'],
        ['paragraph', 'So do "smart" quotes... and CDC advice.[1]'],
        ['heading', 'Who should get one?'],
        ['list_item', 'Everyone six months and older'],
        ['list_item', 'Especially:'],
        ['list_item', 'People over 65'],
        ['list_item', 'Anyone who is pregnant'],
        ['paragraph', 'Health care workers'],
        ['paragraph', 'The single best thing you can do is get vaccinated.'],
        ['other', '— A physician'],
        ['code', "curl -X POST https://api.example.test/appointments \\\n-d 'type=flu'"],
        ['caption', 'Our downtown clinic'],
        ['caption', 'Doses given'],
        ['cell', 'Year'],
        ['cell', 'Doses'],
        ['cell', '2024'],
        ['cell', '12,400'],
        ['paragraph', 'Legal boilerplate a site would tell us to ignore.'],
        ['other', "4301 W. Markham St.\nLittle Rock, AR 72205"],
        ['other', 'Text before a nested block'],
        ['paragraph', 'The nested block'],
        ['other', 'and text after it'],
        ['other', 'Search'],
        ['heading', 'References'],
        ['list_item', 'CDC. Key facts about seasonal flu vaccine. 2025.'],
        ['other', 'Text in a custom element'],
        ['other', '© 2026 Example Health'],
    ]);
});

it('drops what a reader never reads: the head, scripts, styles, media, controls, comments and hidden content', function () {
    $text = (new HtmlExtractor)->extract(readFixture('messy.html'))->text();

    expect($text)
        ->not->toContain('dataLayer')
        ->not->toContain('margin')
        ->not->toContain('The front of the clinic')       // an image's alt text
        ->not->toContain('does not support video')
        ->not->toContain('An icon')                       // an svg title
        ->not->toContain('Go')                            // a button
        ->not->toContain('Hidden from the page')
        ->not->toContain('Same, by style')
        ->not->toContain('a comment about nothing');

    // The page title is in the head, and the head is dropped; the h1 with
    // the same words is what the reader sees.
    expect(substr_count($text, 'Flu shots & you'))->toBe(1);
});

it('records where each block sits: its tag, what encloses it, and every class on the way down', function () {
    $blocks = (new HtmlExtractor)->extract(readFixture('messy.html'))->blocks;

    $lead = $blocks[2];
    expect($lead->origin->tag)->toBe('p');
    expect($lead->origin->ancestors)->toBe(['main', 'article']);
    expect($lead->origin->classes)->toBe(['entry', 'lead']);
    expect($lead->origin->path)->toBeNull();

    $nested = $blocks[8];
    expect($nested->origin->ancestors)->toBe(['main', 'article', 'ul', 'li', 'ul']);
    expect($nested->isListItem())->toBeTrue();

    expect($blocks[10]->isListItem())->toBeTrue();     // <li><p>…</p></li>
    expect($blocks[11]->isQuoted())->toBeTrue();       // a paragraph in the blockquote
    expect($blocks[12]->isQuoted())->toBeTrue();       // the attribution, also in it
    expect($blocks[13]->isCode())->toBeTrue();
    expect($blocks[19]->isInTable())->toBeTrue();
    expect($blocks[20]->hasClass('no-readability'))->toBeTrue();
    expect($blocks[21]->origin->tag)->toBe('address');
    expect($blocks[27]->hasClass('references'))->toBeTrue();
});

it('numbers headings by their level', function () {
    $blocks = (new HtmlExtractor)->extract('<h1>One</h1><h3>Three</h3><h6>Six</h6><p>None</p>')->blocks;

    expect(array_map(fn ($b) => [$b->kind, $b->level], $blocks))->toBe([
        [BlockKind::Heading, 1], [BlockKind::Heading, 3], [BlockKind::Heading, 6], [BlockKind::Paragraph, 0],
    ]);
});

it('keeps inline markup inside its block and does not invent spaces the markup did not have', function () {
    $document = (new HtmlExtractor)->extract('<p>Re<b>use</b>, <em>then</em> <a href="/x">recycle</a>.</p>');

    expect(outline($document))->toBe([['paragraph', 'Reuse, then recycle.']]);
});

it('treats a line break as a hard break and the source\'s own line wrapping as nothing', function () {
    $document = (new HtmlExtractor)->extract("<p>Call us\n  today<br>\n  Monday to Friday<br/><br>9 to 5</p>");

    expect($document->text())->toBe("Call us today\nMonday to Friday\n9 to 5");
});

it('keeps the lines of preformatted text', function () {
    $document = (new HtmlExtractor)->extract("<pre>line one\n    line two\n\n\nline three</pre>");

    expect($document->text())->toBe("line one\nline two\nline three");
});

it('decodes entities and reads UTF-8 without a charset declaration', function () {
    $document = (new HtmlExtractor)->extract('<p>Caf&eacute; &amp; crème brûlée — 5&nbsp;&euro; “yes”</p>');

    expect($document->text())->toBe('Café & crème brûlée — 5 € "yes"');
});

it('reads a bare fragment with no body, and bare text with no element at all', function () {
    expect(outline((new HtmlExtractor)->extract('<p>One</p><p>Two</p>')))->toBe([['paragraph', 'One'], ['paragraph', 'Two']]);
    expect(outline((new HtmlExtractor)->extract('Just words')))->toBe([['other', 'Just words']]);
});

it('produces no blocks for markup that says nothing', function () {
    expect((new HtmlExtractor)->extract('')->isEmpty())->toBeTrue();
    expect((new HtmlExtractor)->extract("  \n ")->isEmpty())->toBeTrue();
    expect((new HtmlExtractor)->extract('<div><p>  </p><ul><li></li></ul><script>x()</script></div>')->isEmpty())->toBeTrue();
});

it('refuses a node tree, which is the other extractor\'s job', function () {
    (new HtmlExtractor)->extract([['type' => 'paragraph']]);
})->throws(InvalidArgumentException::class, 'BardExtractor');

it('records ids and roles on the way down, which is how a careful site marks its references', function () {
    $blocks = (new HtmlExtractor)->extract(
        '<section id="notes" role="doc-endnotes"><ol><li id="fn1" role="doc-endnote">A note.</li></ol></section><p>Prose.</p>'
    )->blocks;

    expect($blocks[0]->origin->ids)->toBe(['notes', 'fn1']);
    expect($blocks[0]->origin->roles)->toBe(['doc-endnotes', 'doc-endnote']);
    expect($blocks[1]->origin->ids)->toBe([]);
    expect($blocks[1]->origin->roles)->toBe([]);
});

it('leaves out ignored tags and ignored classes, inline or block, when told to', function () {
    $html = '<p>Run <code>composer install</code> first. <span class="no-readability">Ignore me.</span> Then go.</p>'
        .'<div class="no-readability"><p>Whole block ignored.</p></div><p>Kept.</p>';

    expect((new HtmlExtractor)->extract($html)->text())
        ->toBe("Run composer install first. Ignore me. Then go.\n\nWhole block ignored.\n\nKept.");

    expect((new HtmlExtractor(ignoredTags: ['code'], ignoredClasses: ['no-readability']))->extract($html)->text())
        ->toBe("Run first. Then go.\n\nKept.");
});
