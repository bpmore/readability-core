<?php

declare(strict_types=1);

use Bpmore\ReadabilityCore\Exclusion\Addresses;
use Bpmore\ReadabilityCore\Exclusion\Code;
use Bpmore\ReadabilityCore\Exclusion\References;
use Bpmore\ReadabilityCore\Extraction\BardExtractor;
use Bpmore\ReadabilityCore\Extraction\HtmlExtractor;
use Bpmore\ReadabilityCore\Extraction\PlainTextExtractor;
use Bpmore\ReadabilityCore\Text\Block;
use Bpmore\ReadabilityCore\Text\BlockKind;
use Bpmore\ReadabilityCore\Text\Document;
use Bpmore\ReadabilityCore\Text\Origin;

/**
 * What the author wrote, kept on every block an extractor makes, and a
 * position in the cleaned text carried back to it.
 */

/** The written text a stretch of a block's cleaned text came from. */
function writtenOf(Block $block, string $needle): string
{
    [$from, $to] = $block->writtenSpan(strpos($block->text, $needle), strlen($needle));

    return substr((string) $block->written, $from, $to - $from);
}

it('keeps a Bard paragraph as written, inline code and all, and maps back into it', function () {
    $bard = [['type' => 'paragraph', 'content' => [
        ['type' => 'text', 'text' => "Run \u{201C}the  report\u{201D} with "],
        ['type' => 'text', 'text' => 'npm run build', 'marks' => [['type' => 'code']]],
        ['type' => 'text', 'text' => ' first.'],
        ['type' => 'hardBreak'],
        ['type' => 'text', 'text' => "Then call\u{00A0}us."],
    ]]];

    $block = (new BardExtractor(ignoredMarks: Code::INLINE_MARKS))->extract($bard)->blocks[0];

    expect($block->written)->toBe("Run \u{201C}the  report\u{201D} with npm run build first.\nThen call\u{00A0}us.")
        ->and($block->text)->toBe("Run \"the report\" with first.\nThen call us.")
        ->and(writtenOf($block, '"the report"'))->toBe("\u{201C}the  report\u{201D}")
        ->and(writtenOf($block, 'first.'))->toBe('first.')
        ->and(writtenOf($block, 'call us'))->toBe("call\u{00A0}us")
        ->and($block->writtenSpan(strpos($block->text, 'first'), 5)[0])->toBe(strpos($block->written, 'first'));
});

it('keeps HTML and plain text as written too', function () {
    $html = (new HtmlExtractor)->extract("<p>One  two\nthree&nbsp;four.</p>")->blocks[0];
    $plain = (new PlainTextExtractor)->extract("First  para.\n\nSecond\u{2026} para.")->blocks;

    expect($html->written)->toBe("One  two\nthree\u{00A0}four.")
        ->and(writtenOf($html, 'three four'))->toBe("three\u{00A0}four")
        ->and($plain[1]->written)->toBe("Second\u{2026} para.")
        ->and(writtenOf($plain[1], '...'))->toBe("\u{2026}");
});

it('keeps positions through an address cut out of a sentence', function () {
    $document = (new HtmlExtractor)->extract('<p>Visit us at 4301 W. Markham St., Little Rock, AR 72205 or call (501) 686-7000 today.</p>');
    $block = (new Addresses)->apply($document)->blocks[0];

    expect($block->text)->toBe('Visit us at or call today.')
        ->and(writtenOf($block, 'today'))->toBe('today')
        ->and($block->writtenSpan(strpos($block->text, 'today'), 5)[0])->toBe(strpos((string) $block->written, 'today'));
});

it('keeps positions through citations cut out, and through the tidying every block gets', function () {
    $document = (new HtmlExtractor)->extract('<p>Flu shots work [1] , says the CDC (Smith et al., 2019) , so get one.</p>');
    $block = (new References)->apply($document)->blocks[0];

    // The comma beside each citation goes with it; the old Tidy did the same.
    expect($block->text)->toBe('Flu shots work says the CDC so get one.')
        ->and(writtenOf($block, 'says'))->toBe('says')
        ->and(writtenOf($block, 'get one.'))->toBe('get one.');
});

it('drops positions, and keeps what was written, when text is changed without tracking', function () {
    $block = (new HtmlExtractor)->extract('<p>Plain words.</p>')->blocks[0]->withText('Plain.');

    expect($block->positions)->toBeNull()
        ->and($block->writtenSpan(0, 5))->toBeNull()
        ->and($block->written)->toBe('Plain words.');
});

it('has no positions for a block built by hand, as before', function () {
    $block = new Block(BlockKind::Paragraph, 'Text.', new Origin('p'));

    expect($block->written)->toBeNull()->and($block->writtenSpan(0, 4))->toBeNull()
        ->and((new Document([$block]))->blocks[0]->text)->toBe('Text.');
});
