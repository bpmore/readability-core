<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Rule;

use ArrayIterator;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use Traversable;

/**
 * A site's jargon: the words and phrases its authors reach for and its
 * readers do not know, each with what to say instead. The sticky feature,
 * because a health system's list of forbidden internal acronyms is an
 * afternoon of institutional knowledge nobody wants to build twice.
 *
 * Built from a plain array in whichever shape a site file takes:
 *
 *     'utilize' => 'use',
 *     'in order to' => ['to'],
 *     'please be advised that' => [],                        // banned: say it another way
 *     'HbA1c' => ['replacements' => ['A1C', 'blood sugar test'], 'note' => 'Patients know A1C.'],
 *
 * Terms are matched without regard to case. A site's dictionary is laid
 * over the starter with merge(), and comes back out with toArray() for
 * export.
 *
 * @implements IteratorAggregate<string, DictionaryEntry>
 */
final class Dictionary implements Countable, IteratorAggregate
{
    /** @var array<string, DictionaryEntry> keyed by the term, lowercased */
    private readonly array $entries;

    /**
     * @param  list<DictionaryEntry>  $entries
     */
    public function __construct(array $entries = [])
    {
        $keyed = [];

        foreach ($entries as $entry) {
            $keyed[self::key($entry->term)] = $entry;
        }

        $this->entries = $keyed;
    }

    public static function empty(): self
    {
        return new self;
    }

    /**
     * The starter: plainlanguage.gov's substitutions, US federal work in
     * the public domain. What a site starts from and adds to.
     */
    public static function starter(): self
    {
        $file = __DIR__.'/../../resources/dictionaries/plain-language.json';
        $data = json_decode((string) file_get_contents($file), true);

        if (! is_array($data)) {
            throw new InvalidArgumentException('The starter dictionary at '.$file.' could not be read.');
        }

        return self::fromArray(array_filter($data, static fn (string $term): bool => ! str_starts_with($term, '_'), ARRAY_FILTER_USE_KEY));
    }

    /**
     * @param  array<string, mixed>  $terms  term => replacement, list of replacements, null/[] for banned, or ['replacements' => [...], 'note' => '...']
     */
    public static function fromArray(array $terms): self
    {
        $entries = [];

        foreach ($terms as $term => $value) {
            $term = trim((string) $term);

            if ($term === '') {
                continue;
            }

            $note = null;

            if (is_array($value) && array_key_exists('replacements', $value)) {
                $note = isset($value['note']) && is_string($value['note']) && trim($value['note']) !== '' ? trim($value['note']) : null;
                $value = $value['replacements'];
            }

            $replacements = array_values(array_filter(array_map(
                static fn (mixed $r): string => trim((string) $r),
                is_array($value) ? $value : [$value ?? ''],
            ), static fn (string $r): bool => $r !== ''));

            $entries[] = new DictionaryEntry($term, $replacements, $note);
        }

        return new self($entries);
    }

    /** This dictionary with another laid over it: the other's entry wins where both have a term. */
    public function merge(self $other): self
    {
        return new self([...array_values($this->entries), ...array_values($other->entries)]);
    }

    public function without(string ...$terms): self
    {
        $drop = array_map(self::key(...), $terms);

        return new self(array_values(array_filter($this->entries, static fn (DictionaryEntry $e): bool => ! in_array(self::key($e->term), $drop, true))));
    }

    public function has(string $term): bool
    {
        return isset($this->entries[self::key($term)]);
    }

    public function get(string $term): ?DictionaryEntry
    {
        return $this->entries[self::key($term)] ?? null;
    }

    /**
     * @return list<DictionaryEntry>
     */
    public function entries(): array
    {
        return array_values($this->entries);
    }

    /**
     * For export: the shape fromArray() reads.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $out = [];

        foreach ($this->entries as $entry) {
            $out[$entry->term] = $entry->note === null
                ? $entry->replacements
                : ['replacements' => $entry->replacements, 'note' => $entry->note];
        }

        return $out;
    }

    public function count(): int
    {
        return count($this->entries);
    }

    /**
     * @return Traversable<string, DictionaryEntry>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->entries);
    }

    private static function key(string $term): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $term)));
    }
}
