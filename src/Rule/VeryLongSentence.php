<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Rule;

use Bpmore\ReadabilityCore\Text\Document;
use Bpmore\ReadabilityCore\Text\EnglishSegmenter;
use Bpmore\ReadabilityCore\Text\Segmenter;

/**
 * A sentence twice as long as a reader can follow: over 2N words, 50 by
 * default. An error, and one of the two things a gate may block on,
 * because a 60-word sentence is not a matter of taste.
 */
final class VeryLongSentence extends ConfiguredRule
{
    public const HANDLE = 'very_long_sentence';

    private readonly Segmenter $segmenter;

    /**
     * @param  array<string, mixed>  $options  `words`, `severity`
     * @param  int  $default  the threshold when the options do not give one: twice the long-sentence limit
     */
    public function __construct(array $options = [], private readonly int $default = 2 * LongSentence::DEFAULT_WORDS, ?Segmenter $segmenter = null)
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
        return Severity::Error;
    }

    public function words(): int
    {
        return $this->intOption('words', $this->default);
    }

    public function check(Document $document): array
    {
        $limit = $this->words();
        $findings = [];

        foreach (Sentences::of($document, $this->segmenter) as [$block, $sentence, $count]) {
            if ($count <= $limit) {
                continue;
            }

            $findings[] = $this->finding(
                sprintf('This sentence runs to %d words, far past the %d a reader can follow. Break it into three or four.', $count, $limit),
                Sentences::location($block, $sentence),
                $sentence->text,
            );
        }

        return $findings;
    }
}
