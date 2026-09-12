<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Rule;

/**
 * One term a site would rather its authors did not use, and what to say
 * instead. No replacements means the term is simply not to be used: an
 * internal acronym, a brand nobody outside the building knows.
 */
final class DictionaryEntry
{
    /**
     * @param  list<string>  $replacements  in order of preference; the first is the suggestion
     */
    public function __construct(
        public readonly string $term,
        public readonly array $replacements = [],
        /** Why, or how, when the replacements alone do not say it. */
        public readonly ?string $note = null,
    ) {}

    public function isBanned(): bool
    {
        return $this->replacements === [];
    }

    public function suggestion(): ?string
    {
        return $this->replacements[0] ?? null;
    }
}
