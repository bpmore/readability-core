<?php

declare(strict_types=1);

use Bpmore\ReadabilityCore\Exclusion\Code;
use Bpmore\ReadabilityCore\Exclusion\IgnoredClasses;
use Bpmore\ReadabilityCore\Exclusion\IgnoredSets;
use Bpmore\ReadabilityCore\Exclusion\Quotes;
use Bpmore\ReadabilityCore\Extraction\BardExtractor;
use Bpmore\ReadabilityCore\Extraction\HtmlExtractor;

it('drops code blocks and what is inside them, and names the inline elements the extractors should drop', function () {
    $document = (new HtmlExtractor)->extract('<p>Run it:</p><pre><code>composer install</code></pre><p>Done.</p>');

    expect(texts((new Code)->apply($document)))->toBe(['Run it:', 'Done.']);
    expect(Code::INLINE_TAGS)->toContain('code');
    expect(Code::INLINE_MARKS)->toBe(['code']);
});

it('drops quotations with their attribution, from either extractor', function () {
    $html = (new HtmlExtractor)->extract('<p>Ours.</p><blockquote><p>Theirs.</p><footer>— Them</footer></blockquote><p>Ours again.</p>');
    $bard = (new BardExtractor)->extract([
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Ours.']]],
        ['type' => 'blockquote', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Theirs.']]]]],
    ]);

    expect(texts((new Quotes)->apply($html)))->toBe(['Ours.', 'Ours again.']);
    expect(texts((new Quotes)->apply($bard)))->toBe(['Ours.']);
});

it('drops blocks inside an element with an ignored class, and does nothing with no classes to ignore', function () {
    $document = (new HtmlExtractor)->extract('<p>Keep.</p><div class="legal no-readability"><p>Drop.</p></div><p class="aside">Keep too.</p>');

    expect(texts((new IgnoredClasses(['no-readability']))->apply($document)))->toBe(['Keep.', 'Keep too.']);
    expect(texts((new IgnoredClasses(['aside']))->apply($document)))->toBe(['Keep.', 'Drop.']);
    expect(texts((new IgnoredClasses([]))->apply($document)))->toBe(['Keep.', 'Drop.', 'Keep too.']);
});

it('drops blocks inside a Bard set with an ignored handle', function () {
    $document = (new BardExtractor)->extract(readFixture('bard.json'));

    expect(texts((new IgnoredSets(['callout']))->apply($document)))->not->toContain('Call us if your symptoms get worse.');
    expect(texts((new IgnoredSets(['code']))->apply($document)))->toContain('Call us if your symptoms get worse.');
    expect((new IgnoredSets([]))->apply($document))->toHaveCount(count($document));
});
