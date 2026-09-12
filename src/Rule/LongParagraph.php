<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Rule;

use Bpmore\ReadabilityCore\Text\Document;
use Bpmore\ReadabilityCore\Text\EnglishSegmenter;
use Bpmore\ReadabilityCore\Text\Segmenter;
use Bpmore\ReadabilityCore\Text\Words;

/**
 * A paragraph a reader cannot see the shape of: over N words, 150 by
 * default, or over N sentences, eight by default. Either alone is
 * enough. One idea per paragraph is the plain-language rule, and a
 * paragraph this long is holding several.
 *
 * Every block but a heading is a paragraph here. A 160-word bullet is a
 * paragraph in disguise.
 */
final class LongParagraph extends ConfiguredRule
{
    public const HANDLE = 'long_paragraph';

    public const DEFAULT_WORDS = 150;

    public const DEFAULT_SENTENCES = 8;

    private readonly Segmenter $segmenter;

    /**
     * @param  array<string, mixed>  $options  `words`, `sentences`, `severity`
     */
    public function __construct(array $options = [], ?Segmenter $segmenter = null)
    {
        parent::__construct($options);
        $this->segmenter = $segmenter ?? new EnglishSegmenter;
    }

    public function handle(): string
    {
        return self::HANDLE;
    }

    protected function defaultSeverity(): Severity
    {
        return Severity::Warning;
    }

    public function words(): int
    {
        return $this->intOption('words', self::DEFAULT_WORDS);
    }

    public function sentences(): int
    {
        return $this->intOption('sentences', self::DEFAULT_SENTENCES);
    }

    public function check(Document $document): array
    {
        $findings = [];

        foreach ($document->blocks as $index => $block) {
            if ($block->isHeading()) {
                continue;
            }

            $sentences = $this->segmenter->sentences($block->text);
            $words = count(Words::of($block->text));
            $tooManyWords = $words > $this->words();
            $tooManySentences = count($sentences) > $this->sentences();

            if (! $tooManyWords && ! $tooManySentences) {
                continue;
            }

            $message = match (true) {
                $tooManyWords && $tooManySentences => sprintf('This paragraph runs to %d words in %d sentences. Split it; one idea per paragraph, and about %d words at most.', $words, count($sentences), $this->words()),
                $tooManyWords => sprintf('This paragraph runs to %d words. Split it; one idea per paragraph, and about %d words at most.', $words, $this->words()),
                default => sprintf('This paragraph has %d sentences. Split it; past %d a reader loses the shape of it.', count($sentences), $this->sentences()),
            };

            $findings[] = $this->finding($message, new Location($index, 0, strlen($block->text)), $sentences[0]->text ?? '');
        }

        return $findings;
    }
}
