<?php

declare(strict_types=1);

use Bpmore\ReadabilityCore\Exclusion\References;
use Bpmore\ReadabilityCore\Extraction\HtmlExtractor;

const PAGE_WITH_REFERENCES = <<<'HTML'
<h1>Flu shots</h1>
<p>Vaccines work (Smith et al., 2019; Jones 2021).[1] They are safe.[2,3] As Smith et al. showed, side effects are mild (see Table 2).</p>
<h2>Notes for patients</h2>
<p>Bring your card.</p>
<h2>References</h2>
<ol><li>Smith J, Jones K. Vaccine safety. Lancet. 2019;12(3):45-67.</li><li>Second one, no marks at all.</li></ol>
<h3>A sub-heading inside references</h3>
<p>Still references.</p>
<h2>After the references</h2>
<p>Back to prose.</p>
<div class="post-footnotes"><p>A footnote by class.</p></div>
<section role="doc-endnotes"><p>An endnote by role.</p></section>
<ul><li>Retrieved from https://example.test on 3 May 2024.</li></ul>
<p>Smith, J. A., and Doe, R. (2020). A paper. Journal of Things, vol. 3, pp. 1-9.</p>
<p>In 2020 (the year we opened) we saw (a lot of) patients.</p>
HTML;

it('drops the section under a heading that says references, to the next heading of that level', function () {
    $texts = texts((new References)->apply((new HtmlExtractor)->extract(PAGE_WITH_REFERENCES)));

    expect($texts)->toContain('Notes for patients', 'Bring your card.', 'After the references', 'Back to prose.');
    expect($texts)->not->toContain('References', 'Second one, no marks at all.', 'A sub-heading inside references', 'Still references.');
});

it('drops blocks marked as references by class, id or role', function () {
    $texts = texts((new References)->apply((new HtmlExtractor)->extract(PAGE_WITH_REFERENCES)));

    expect($texts)->not->toContain('A footnote by class.', 'An endnote by role.');
    expect(texts((new References)->apply((new HtmlExtractor)->extract('<ol id="citations"><li>One.</li></ol><p>Prose.</p>'))))->toBe(['Prose.']);
});

it('drops a block that reads like a citation: one mark for a list item, two for a paragraph', function () {
    $texts = texts((new References)->apply((new HtmlExtractor)->extract(PAGE_WITH_REFERENCES)));

    expect($texts)->not->toContain('Retrieved from https://example.test on 3 May 2024.');
    expect($texts)->not->toContain('Smith, J. A., and Doe, R. (2020). A paper. Journal of Things, vol. 3, pp. 1-9.');

    // "et al." alone does not make a paragraph a citation.
    expect($texts)->toContain('Vaccines work. They are safe. As Smith et al. showed, side effects are mild (see Table 2).');
});

it('cuts citation marks out of a sentence and leaves ordinary brackets alone', function () {
    $texts = texts((new References)->apply((new HtmlExtractor)->extract(PAGE_WITH_REFERENCES)));

    expect($texts[1])->toBe('Vaccines work. They are safe. As Smith et al. showed, side effects are mild (see Table 2).');
    expect($texts)->toContain('In 2020 (the year we opened) we saw (a lot of) patients.');
});

it('ends a references section where the element holding its heading ends, so a page footer is not a reference', function () {
    $document = (new HtmlExtractor)->extract('<article><h2>Sources</h2><ol><li>A source.</li></ol></article><footer>© 2026 Example</footer>');

    expect(texts((new References)->apply($document)))->toBe(['© 2026 Example']);
});

it('does not take "Notes for patients" for a notes section', function () {
    $document = (new HtmlExtractor)->extract('<h2>Notes for patients</h2><p>Keep.</p><h2>Notes</h2><p>Drop.</p>');

    expect(texts((new References)->apply($document)))->toBe(['Notes for patients', 'Keep.']);
});
