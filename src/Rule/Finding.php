<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Rule;

/**
 * One thing a rule found, in words that say what to do about it.
 *
 * The message is written for the author, not the developer: "This
 * sentence is 34 words; try breaking it in two", never "long_sentence
 * threshold exceeded". The handle is there for the gate and the config;
 * the person sees the message.
 */
final class Finding
{
    public function __construct(
        /** The rule's handle: `long_sentence`. What a gate blocks on. */
        public readonly string $rule,
        public readonly Severity $severity,
        /** What to do, in plain words. */
        public readonly string $message,
        /** Where, or null for a finding about the document as a whole, like a rate. */
        public readonly ?Location $location = null,
        /** The sentence or phrase the finding is about, as the author wrote it. */
        public readonly string $excerpt = '',
        /** Something to say instead, when the rule has one: a jargon replacement. */
        public readonly ?string $suggestion = null,
    ) {}

    public function isAboutWholeDocument(): bool
    {
        return $this->location === null;
    }
}
