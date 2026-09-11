<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Locale;

/**
 * Whether a site's text may be analysed, and if not, why, in words a
 * person can act on. The host shows the message in place of a score.
 */
final class Decision
{
    private function __construct(
        public readonly bool $allowed,
        /** The locale as it was given. */
        public readonly string $locale,
        /** The language part of it, lowercased: "fr" for "fr_CA". Empty when none was given. */
        public readonly string $language,
        private readonly ?string $message,
    ) {}

    public static function allow(string $locale, string $language): self
    {
        return new self(true, $locale, $language, null);
    }

    public static function refuse(string $locale, string $language, string $message): self
    {
        return new self(false, $locale, $language, $message);
    }

    /** Why analysis is off, in English. Null when it is on. A host with translations has the locale and language to write its own. */
    public function message(): ?string
    {
        return $this->message;
    }
}
