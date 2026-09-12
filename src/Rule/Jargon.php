<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Rule;

use Bpmore\ReadabilityCore\Text\Document;
use InvalidArgumentException;

/**
 * Words and phrases on the site's dictionary, each with what to say
 * instead. One finding per occurrence, because each one is an edit.
 *
 * Matching is whole words, any case. A single-word term also catches
 * its inflections, so "utilize" finds "utilized" and "utilizing". Where
 * two terms overlap in the text ("in order to" and "order"), the longer
 * one is the finding and the shorter is not repeated inside it.
 */
final class Jargon extends ConfiguredRule
{
    public const HANDLE = 'jargon';

    private readonly Dictionary $dictionary;

    /** @var list<array{string, DictionaryEntry}> patterns with their entries, longest term first */
    private readonly array $patterns;

    /**
     * @param  array<string, mixed>  $options  `dictionary` (a Dictionary, an array of terms, or 'default' for the starter), `severity`
     * @param  ?Dictionary  $dictionary  given directly, which is how a host with a stored per-site dictionary does it; overrides the option
     */
    public function __construct(array $options = [], ?Dictionary $dictionary = null)
    {
        parent::__construct($options);
        $this->dictionary = $dictionary ?? self::resolve($this->option('dictionary', 'default'));

        $entries = $this->dictionary->entries();
        usort($entries, static fn (DictionaryEntry $a, DictionaryEntry $b): int => mb_strlen($b->term) <=> mb_strlen($a->term));

        $this->patterns = array_map(static fn (DictionaryEntry $e): array => [Phrases::pattern($e->term, inflections: true), $e], $entries);
    }

    public function handle(): string
    {
        return self::HANDLE;
    }

    protected function defaultSeverity(): Severity
    {
        return Severity::Warning;
    }

    public function dictionary(): Dictionary
    {
        return $this->dictionary;
    }

    public function check(Document $document): array
    {
        $findings = [];

        foreach ($document->blocks as $index => $block) {
            foreach (Phrases::findAll($block->text, $this->patterns) as [$offset, $written, $entry]) {
                $findings[] = $this->finding($this->message($entry, $written), new Location($index, $offset, strlen($written)), $written, $entry->suggestion());
            }
        }

        return $findings;
    }

    private function message(DictionaryEntry $entry, string $written): string
    {
        $message = match (count($entry->replacements)) {
            0 => sprintf('"%s" is on the site\'s list of words to avoid. Say it another way.', $written),
            1 => sprintf('Say "%s" instead of "%s".', $entry->replacements[0], $written),
            default => sprintf('Say "%s" instead of "%s".', implode('" or "', $entry->replacements), $written),
        };

        return $entry->note === null ? $message : $message.' '.$entry->note;
    }

    /**
     * @param  mixed  $option  the `dictionary` option
     */
    private static function resolve(mixed $option): Dictionary
    {
        return match (true) {
            $option instanceof Dictionary => $option,
            is_array($option) => Dictionary::fromArray($option),
            $option === null, $option === '', $option === 'default' => Dictionary::starter(),
            $option === 'none' => Dictionary::empty(),
            default => throw new InvalidArgumentException(sprintf(
                'The jargon rule cannot load a dictionary called "%s". Give it a Dictionary, an array of terms, or "default" for the starter.',
                is_scalar($option) ? (string) $option : gettype($option),
            )),
        };
    }
}
