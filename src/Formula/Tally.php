<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Formula;

use Bpmore\ReadabilityCore\Text\Block;
use Bpmore\ReadabilityCore\Text\Document;
use Bpmore\ReadabilityCore\Text\Segmenter;
use Bpmore\ReadabilityCore\Text\SyllableCounter;
use Bpmore\ReadabilityCore\Text\Words;

/**
 * Counts a document the way the formulas want it counted.
 *
 * Headings are left out. They are section markers, not sentences, and a
 * page whose every "Who should get one?" counts as a one-line sentence
 * comes out easier than it reads. List items are in: a bulleted list is
 * a plain-language technique, and the formulas rewarding one is right.
 *
 * A number is a one-syllable word ("5 minutes" is two words); a token
 * with no letter or digit in it is not a word at all.
 */
final class Tally
{
    public function __construct(
        private readonly Segmenter $segmenter,
        private readonly SyllableCounter $syllables,
    ) {}

    public function of(Document $document): Counts
    {
        $counts = Counts::none();

        foreach ($document as $block) {
            if (! $block->isHeading()) {
                $counts = $counts->add($this->ofBlock($block));
            }
        }

        return $counts;
    }

    public function ofBlock(Block $block): Counts
    {
        return $this->ofText($block->text);
    }

    public function ofText(string $text): Counts
    {
        $sentences = count($this->segmenter->sentences($text));
        $words = 0;
        $syllables = 0;
        $characters = 0;
        $complex = 0;
        $poly = 0;

        foreach (Words::of($text) as $word) {
            $count = max(1, $this->syllables->syllables($word));

            $words++;
            $syllables += $count;
            $characters += Words::characters($word);

            if ($count >= 3) {
                $poly++;
                $complex += $this->isComplex($word, $count) ? 1 : 0;
            }
        }

        return new Counts($sentences, $words, $syllables, $characters, $complex, $poly);
    }

    /**
     * Gunning's complex word: three syllables or more, but not a compound
     * ("well-being"), and not a word that only reaches three because of
     * an "-es", "-ed" or "-ing" on the end ("attended", "beginning").
     * Proper nouns, his other exclusion, are gone before this runs.
     */
    private function isComplex(string $word, int $syllables): bool
    {
        if (str_contains($word, '-')) {
            return false;
        }

        if (preg_match('/(es|ed|ing)$/i', $word) === 1) {
            $stem = (string) preg_replace('/(es|ed|ing)$/i', '', $word);

            return max(1, $this->syllables->syllables($stem)) >= 3;
        }

        return true;
    }
}
