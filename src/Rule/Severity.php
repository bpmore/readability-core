<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Rule;

use InvalidArgumentException;

/**
 * How much a finding matters. An error is the kind of thing a gate may
 * block on; a warning asks for attention; a tip is a suggestion and
 * nothing more. Every rule has a default, and a site's config can move it.
 */
enum Severity: string
{
    case Error = 'error';
    case Warning = 'warning';
    case Tip = 'tip';

    /**
     * From a config value, or the rule's default when there is none. A
     * value that is not a severity is a mistake in the config, and is
     * said so rather than quietly becoming the default.
     */
    public static function fromConfig(mixed $value, self $default): self
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if ($value instanceof self) {
            return $value;
        }

        return self::tryFrom(strtolower((string) $value))
            ?? throw new InvalidArgumentException(sprintf(
                '"%s" is not a severity. Use one of: %s.',
                (string) $value,
                implode(', ', array_map(static fn (self $s): string => $s->value, self::cases())),
            ));
    }

    /** Ordered: error before warning before tip. */
    public function rank(): int
    {
        return match ($this) {
            self::Error => 0,
            self::Warning => 1,
            self::Tip => 2,
        };
    }
}
