<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Rule;

/**
 * A rule that takes its options from the site's config, in the shape the
 * spec sketches: `'long_sentence' => ['words' => 25, 'severity' => 'warning']`.
 * Every rule has a default severity a site can move.
 */
abstract class ConfiguredRule implements Rule
{
    protected readonly Severity $severity;

    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(protected readonly array $options = [])
    {
        $this->severity = Severity::fromConfig($options['severity'] ?? null, $this->defaultSeverity());
    }

    abstract protected function defaultSeverity(): Severity;

    /** An option, or the default when the config leaves it out. */
    protected function option(string $key, mixed $default): mixed
    {
        return $this->options[$key] ?? $default;
    }

    /** A whole-number option, or the default when the config leaves it out or gives nonsense. */
    protected function intOption(string $key, int $default): int
    {
        $value = $this->options[$key] ?? null;

        return is_numeric($value) && (int) $value > 0 ? (int) $value : $default;
    }

    /**
     * A finding of this rule, at this severity.
     */
    protected function finding(string $message, ?Location $location = null, string $excerpt = '', ?string $suggestion = null): Finding
    {
        return new Finding($this->handle(), $this->severity, $message, $location, $excerpt, $suggestion);
    }
}
