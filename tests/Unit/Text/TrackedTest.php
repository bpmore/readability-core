<?php

declare(strict_types=1);

use Bpmore\ReadabilityCore\Exclusion\Tidy;
use Bpmore\ReadabilityCore\Text\Normalizer;
use Bpmore\ReadabilityCore\Text\Tracked;

/**
 * The cleaning, done keeping track of where every byte came from. Two
 * promises: the cleaned text is exactly what it was before tracking existed
 * (held against copies of the old code), and a position in it leads back to
 * the right place in what was written.
 */

/** Normalizer::normalize() as it was before positions, kept as the oracle. */
function legacyNormalize(string $text): string
{
    if (! mb_check_encoding($text, 'UTF-8')) {
        $text = mb_scrub($text, 'UTF-8');
    }
    if (class_exists(\Normalizer::class)) {
        $text = \Normalizer::normalize($text, \Normalizer::FORM_C) ?: $text;
    }
    $text = str_replace(mb_str_split("\u{00AD}\u{200B}\u{200C}\u{200D}\u{2060}\u{FEFF}"), '', $text);
    $text = strtr($text, ["\u{2018}" => "'", "\u{2019}" => "'", "\u{201A}" => "'", "\u{2032}" => "'", "\u{201C}" => '"', "\u{201D}" => '"', "\u{201E}" => '"', "\u{2033}" => '"', "\u{2026}" => '...']);
    $text = (string) preg_replace('/\p{Zs}/u', ' ', $text);
    $text = (string) preg_replace('/[\x{2028}\x{2029}]/u', "\n", $text);
    $text = (string) preg_replace('/[^\S\n]+/', ' ', $text);
    $text = (string) preg_replace('/ *\n */', "\n", $text);
    $text = (string) preg_replace('/\n{2,}/', "\n", $text);

    return trim($text);
}

/** Tidy::text() as it was before positions. */
function legacyTidy(string $text): string
{
    $lines = [];
    foreach (explode("\n", $text) as $line) {
        $line = (string) preg_replace('/\s*,?\s*'.Tidy::GAP.'\s*,?/u', ' ', $line);
        $line = (string) preg_replace('/\(\s*\)|\[\s*\]|"\s*"|\'\s*\'/', '', $line);
        $line = (string) preg_replace('/[^\S\n]+/', ' ', $line);
        $line = (string) preg_replace('/\s+([.,;:!?)\]])/', '$1', $line);
        $line = (string) preg_replace('/([(\[])\s+/', '$1', $line);
        $line = (string) preg_replace('/([,;:])(?:\s*[,;:])+/', '$1', $line);
        $line = (string) preg_replace('/\s*[,;:]\s*([.!?])/', '$1', $line);
        $line = trim((string) preg_replace('/^[\s,;:]+/', '', $line));
        if (preg_match('/[\p{L}\p{N}]/u', $line) === 1) {
            $lines[] = $line;
        }
    }

    return implode("\n", $lines);
}

/** Strings made of the characters the cleaning cares about, from a fixed seed. */
function awkwardStrings(int $count): array
{
    mt_srand(7);
    $alphabet = ['a', 'b', 'é', "e\u{0301}", 'Ω', '1', '9', ' ', '  ', "\t", "\n", "\r\n", "\u{00A0}", "\u{2009}", "\u{200B}", "\u{00AD}", "\u{FEFF}",
        "\u{2018}", "\u{2019}", "\u{201C}", "\u{201D}", "\u{2026}", "\u{2028}", "\u{2029}", '"', "'", ',', '.', ';', ':', '!', '?', '(', ')', '[', ']', Tidy::GAP, '😀', "\u{1100}\u{1161}", 'Dr.', 'St.'];
    $strings = [];

    for ($i = 0; $i < $count; $i++) {
        $s = '';
        for ($j = 0, $n = mt_rand(0, 40); $j < $n; $j++) {
            $s .= $alphabet[mt_rand(0, count($alphabet) - 1)];
        }
        $strings[] = $s;
    }

    return $strings;
}

it('cleans text exactly as it did before tracking existed', function () {
    foreach (awkwardStrings(3000) as $s) {
        expect(Normalizer::normalize($s))->toBe(legacyNormalize($s), 'for '.json_encode($s));
    }
});

it('tidies text exactly as it did before tracking existed', function () {
    foreach (awkwardStrings(3000) as $s) {
        $s = legacyNormalize($s);
        expect(Tidy::text($s))->toBe(legacyTidy($s), 'for '.json_encode($s));
    }
});

it('leads every word of the cleaned text back to where it starts and ends as written', function () {
    // Each word's first and last letters lead back to exactly those letters.
    // The stretch between them is everything the author wrote there, which
    // can hold something the cleaning took out, like an empty pair of quotes.
    foreach (awkwardStrings(1500) as $written) {
        $tracked = Tidy::track(Normalizer::track(Tracked::of($written)));

        preg_match_all('/[\p{L}\p{N}]+/u', $tracked->text, $words, PREG_OFFSET_CAPTURE);

        foreach ($words[0] as [$word, $offset]) {
            $first = mb_substr($word, 0, 1);
            $last = mb_substr($word, -1);

            [$from] = $tracked->span($offset, strlen($word));
            [$firstFrom, $firstTo] = $tracked->span($offset, strlen($first));
            [$lastFrom, $lastTo] = $tracked->span($offset + strlen($word) - strlen($last), strlen($last));

            expect($firstFrom)->toBe($from)
                ->and(legacyNormalize(substr($written, $firstFrom, $firstTo - $firstFrom)))->toBe($first, 'first letter of '.$word.' in '.json_encode($written))
                ->and(legacyNormalize(substr($written, $lastFrom, $lastTo - $lastFrom)))->toBe($last, 'last letter of '.$word.' in '.json_encode($written));
        }
    }
});

it('maps a straightened quote, a folded space and a cut to where they were written', function () {
    $written = "She said \u{201C}don\u{2019}t\u{201D},  see\u{00A0}Dr.\u{00A0}Smith";
    $clean = Normalizer::track(Tracked::of($written));

    expect($clean->text)->toBe('She said "don\'t", see Dr. Smith');

    $at = fn (string $needle) => $clean->span(strpos($clean->text, $needle), strlen($needle));
    $show = fn (array $span) => substr($written, $span[0], $span[1] - $span[0]);

    expect($show($at('"')))->toBe("\u{201C}")
        ->and($show($at("don't")))->toBe("don\u{2019}t")
        ->and($show($at(', see')))->toBe(',  see')
        ->and($show($at('Smith')))->toBe('Smith')
        ->and($at('Smith')[0])->toBe(strlen($written) - 5);
});

it('carries a captured group\'s own origins through a replacement', function () {
    $tracked = Tracked::of('a  ,b')->replace('/\s+([,])/', '$1');

    expect($tracked->text)->toBe('a,b')
        ->and($tracked->span(1, 1))->toBe([3, 4]);
});

it('splits and joins with the real separators, dropping lines', function () {
    $tracked = Tracked::of("one\n,,\nthree");
    $kept = array_values(array_filter($tracked->split("\n"), fn ($part) => $part[0]->text !== ',,'));
    $joined = $tracked->join($kept);

    expect($joined->text)->toBe("one\nthree")
        ->and($joined->span(4, 5))->toBe([7, 12])
        ->and($joined->span(3, 1))->toBe([3, 4]);
});

it('answers a stretch of nothing, and one at the very end, as a point', function () {
    $tracked = Tracked::of('  abc  ')->trim();

    expect($tracked->text)->toBe('abc')
        ->and($tracked->span(0, 0))->toBe([2, 2])
        ->and($tracked->span(3, 0))->toBe([7, 7]);
});
