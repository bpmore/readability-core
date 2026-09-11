<?php

declare(strict_types=1);

use Bpmore\ReadabilityCore\Exclusion\FiguresInTables;
use Bpmore\ReadabilityCore\Extraction\HtmlExtractor;

it('drops a whole table of figures, headers and caption included', function () {
    $document = (new HtmlExtractor)->extract(
        '<p>Before.</p><table><caption>Doses given</caption><tr><th>Year</th><th>Doses</th></tr><tr><td>2024</td><td>12,400</td></tr></table><p>After.</p>'
    );

    expect(texts((new FiguresInTables)->apply($document)))->toBe(['Before.', 'After.']);
});

it('drops only the figures from a table that is mostly prose', function () {
    $document = (new HtmlExtractor)->extract(
        '<table><tr><td>Walk-in visits are welcome every weekday morning.</td><td>Appointments can be booked online at any time.</td>'
        .'<td>Same-day results are usual for most tests.</td><td>$25</td></tr></table>'
    );

    expect(texts((new FiguresInTables)->apply($document)))->toBe([
        'Walk-in visits are welcome every weekday morning.',
        'Appointments can be booked online at any time.',
        'Same-day results are usual for most tests.',
    ]);
});

it('treats a run of cells as one table, and a unit or a dash as a figure', function () {
    $document = (new HtmlExtractor)->extract('<table><tr><td>5 mg</td><td>—</td><td>N/A</td><td>Once a day with food</td></tr></table><p>Prose.</p>');

    expect(texts((new FiguresInTables)->apply($document)))->toBe(['Prose.']);
});
