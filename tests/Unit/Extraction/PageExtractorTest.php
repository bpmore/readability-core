<?php

declare(strict_types=1);

use Bpmore\ReadabilityCore\Extraction\PageExtractor;

it('reads the main content of a page and nothing around it', function () {
    $document = (new PageExtractor)->extract(readFixture('messy.html'));

    $texts = texts($document);

    expect($texts[0])->toBe('Flu shots & you');
    expect($texts)->not->toContain('Home Find care');
    expect(implode(' ', $texts))->not->toContain('Example Health');

    // What is inside main is read exactly as the HTML extractor reads it,
    // small print and all: what to leave out of the content is the
    // exclusions' decision, not this one's.
    expect($texts)->toContain('Legal boilerplate a site would tell us to ignore.');
});

it('takes the element with role main where a theme has no main tag', function () {
    $page = '<body><div role="navigation">Home About</div><div role="main"><p>We help you.</p></div><div role="contentinfo">Legal.</div></body>';

    expect(texts((new PageExtractor)->extract($page)))->toBe(['We help you.']);
});

it('reads the body without its landmarks where there is no main content', function () {
    $page = <<<'HTML'
        <html><head><title>T</title></head><body>
        <header><nav><a href="/">Home</a></nav><h1>Site</h1></header>
        <div role="banner">Announcement</div>
        <article><header><h1>The page</h1></header><p>We help you.</p><footer>Written last year.</footer></article>
        <aside>Related links</aside>
        <div role="complementary">More links</div>
        <footer>Small print.</footer>
        </body></html>
        HTML;

    // An article's own header and footer go with it, because they are the
    // page's; only the page's furniture is taken away.
    expect(texts((new PageExtractor)->extract($page)))->toBe(['The page', 'We help you.', 'Written last year.']);
});

it('keeps an article header and footer that sit inside main', function () {
    $page = '<body><header>Site</header><main><article><header><h1>Notes</h1></header><p>Read this.</p><footer>By us.</footer></article></main><footer>Legal.</footer></body>';

    expect(texts((new PageExtractor)->extract($page)))->toBe(['Notes', 'Read this.', 'By us.']);
});

it('takes the first main where a page breaks the rule and has two', function () {
    $page = '<body><main><p>First.</p></main><main><p>Second.</p></main></body>';

    expect(texts((new PageExtractor)->extract($page)))->toBe(['First.']);
});

it('survives the round trip with accents intact, and reads an empty page as nothing', function () {
    expect(texts((new PageExtractor)->extract('<main><p>Café résumé naïve.</p></main>')))->toBe(['Café résumé naïve.']);
    expect((new PageExtractor)->extract('   ')->blocks)->toBe([]);
    expect((new PageExtractor)->extract('<body><nav>Only furniture</nav></body>')->blocks)->toBe([]);
});

it('hands ignored classes and tags on to the HTML extractor', function () {
    $page = '<main><p class="no-readability">Skip.</p><p>Keep.</p><figure>Drop.</figure></main>';

    expect(texts((new PageExtractor)->extract($page)))->toBe(['Skip.', 'Keep.', 'Drop.']);
    expect(texts((new PageExtractor(['figure'], ['no-readability']))->extract($page)))->toBe(['Keep.']);
});

it('refuses a node tree, which is the Bard extractor\'s to read', function () {
    (new PageExtractor)->extract([['type' => 'paragraph']]);
})->throws(InvalidArgumentException::class);
