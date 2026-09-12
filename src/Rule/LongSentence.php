<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Rule;

use Bpmore\ReadabilityCore\Text\Document;
use Bpmore\ReadabilityCore\Text\EnglishSegmenter;
use Bpmore\ReadabilityCore\Text\Segmenter;

/**
 * A sentence longer than a reader can hold in their head: over N words,
 * 25 by default. A warning, because one long sentence is a choice and
 * ten are a habit.
 *
 * Stops where VeryLongSentence starts, so a sentence that is far too long
 * is said once, at the higher severity, and not twice.
 */
final class LongSentence extends ConfiguredRule
{
    public const HANDLE = 'long_sentence';

    public const DEFAULT_WORDS = 25;

    private readonly Segmenter $segmenter;

    /**
     * @param  array<string, mixed>  $options  `words`, `severity`
     * @param  ?int  $cap  the very-long threshold, above which the other rule speaks; null when there is none
     */
    public function __construct(array $options = [], private readonly ?int $cap = null, ?Segmenter $segmenter = null)
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

    public function check(Document $document): array
    {
        $limit = $this->words();
        $findings = [];

        foreach (Sentences::of($document, $this->segmenter) as [$block, $sentence, $count]) {
            if ($count <= $limit || ($this->cap !== null && $count > $this->cap)) {
                continue;
            }

            $findings[] = $this->finding(
                sprintf('This sentence runs to %d words. Break it in two; about %d is as many as a reader can follow at once.', $count, $limit),
                Sentences::location($block, $sentence),
                $sentence->text,
            );
        }

        return $findings;
    }
}
