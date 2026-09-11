<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Locale;

/**
 * English only, and says so.
 *
 * Every formula in this package was calibrated on English. Run on German
 * or Spanish they still return a number, and the number means nothing;
 * a page would be graded, published, and trusted on it. So a site whose
 * locale is not on the list gets no score and a message that says why,
 * and a site with no locale at all gets the same, because "unknown" is
 * not a language the formulas know either.
 *
 * The list is the site's `locales` setting. A bare language on it ("en")
 * allows every locale of that language; a full locale ("en_US") allows
 * only itself. Case and the separator do not matter.
 */
final class LocaleGuard
{
    public const SETTING = 'locales';

    /** @var list<string> */
    private readonly array $allowed;

    /**
     * @param  list<string>  $allowed
     */
    public function __construct(array $allowed = ['en'])
    {
        $this->allowed = array_values(array_unique(array_filter(array_map(self::normalize(...), $allowed), static fn (string $s): bool => $s !== '')));
    }

    /**
     * From a site's config: the `locales` list, or English alone without one.
     *
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(array $config): self
    {
        $locales = array_filter((array) ($config[self::SETTING] ?? ['en']), is_string(...));

        return new self(array_values($locales) ?: ['en']);
    }

    public function allows(string $locale): bool
    {
        return $this->check($locale)->allowed;
    }

    public function check(string $locale): Decision
    {
        $normalized = self::normalize($locale);
        $language = explode('_', $normalized)[0];

        if ($normalized === '' || $normalized === 'default') {
            return Decision::refuse($locale, '', $this->noLocaleMessage());
        }

        foreach ($this->allowed as $allowed) {
            if ($allowed === $normalized || (! str_contains($allowed, '_') && $allowed === $language)) {
                return Decision::allow($locale, $language);
            }
        }

        return Decision::refuse($locale, $language, $this->wrongLanguageMessage($locale, $language));
    }

    private function wrongLanguageMessage(string $locale, string $language): string
    {
        $name = self::languageName($language);

        return sprintf(
            'Readability is not checked on this site. Its language is %s, and the formulas behind the score were built on English; '
            .'run on anything else they give a confident number that means nothing. If the content here really is written in English, '
            .'add %s to the %s setting to check it anyway.',
            $name === $language ? 'set to '.$locale : $name.' ('.$locale.')',
            $locale,
            self::SETTING,
        );
    }

    private function noLocaleMessage(): string
    {
        return sprintf(
            'Readability is not checked on this site, because the site has no language set and the formulas behind the score '
            .'only work on English. Set the site\'s locale, and if it is English the check will run.',
        );
    }

    /** "fr" as "French", when intl is here to say so; the code otherwise. */
    private static function languageName(string $language): string
    {
        if (class_exists(\Locale::class)) {
            $name = \Locale::getDisplayLanguage($language, 'en');

            if ($name !== '' && strtolower($name) !== $language) {
                return $name;
            }
        }

        return $language;
    }

    private static function normalize(string $locale): string
    {
        return strtolower(str_replace('-', '_', trim($locale)));
    }
}
