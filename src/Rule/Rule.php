<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Rule;

use Bpmore\ReadabilityCore\Text\Document;

/**
 * One check, one class, testable on its own with a Document and nothing
 * else. A rule is given the document the exclusions have already been
 * applied to, names in, and returns what it found.
 *
 * The formulas are the headline; the rules are what make writing better,
 * because a grade says "harder than you meant" and a rule says which
 * sentence and what to do with it.
 */
interface Rule
{
    /** The config key and the name findings carry: `long_sentence`. */
    public function handle(): string;

    /**
     * @return list<Finding>
     */
    public function check(Document $document): array;
}
