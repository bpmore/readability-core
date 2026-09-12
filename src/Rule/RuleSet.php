<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Rule;

use Bpmore\ReadabilityCore\Text\Document;
use InvalidArgumentException;

/**
 * The rules a site runs, and what they find together.
 *
 * Built from the `rules` section of the site's config: each handle gets
 * its options, a rule the config does not mention runs with its defaults,
 * and `'enabled' => false` leaves one out. A handle nobody has heard of
 * is a mistake in the config and is said so; a rule that is quietly
 * never run is worse than one that fails loudly.
 */
final class RuleSet
{
    /**
     * @param  list<Rule>  $rules
     */
    public function __construct(private readonly array $rules) {}

    /**
     * The rules this package ships, by handle, each a factory taking its
     * own options and the whole `rules` section, for the few rules that
     * need to know about a neighbour. Rules are added here as they are
     * written.
     *
     * @return array<string, callable(array<string, mixed>, array<string, mixed>): Rule>
     */
    public static function registry(): array
    {
        return [
            LongSentence::HANDLE => static fn (array $options, array $all): Rule => new LongSentence($options, cap: self::veryLongThreshold($all)),
            VeryLongSentence::HANDLE => static fn (array $options, array $all): Rule => new VeryLongSentence($options, default: 2 * self::longThreshold($all)),
        ];
    }

    /** The long-sentence limit the config sets, or the default. */
    private static function longThreshold(array $all): int
    {
        return (new LongSentence(is_array($all[LongSentence::HANDLE] ?? null) ? $all[LongSentence::HANDLE] : []))->words();
    }

    /** The very-long limit the config sets or implies, or null when that rule is switched off and long sentences have no ceiling. */
    private static function veryLongThreshold(array $all): ?int
    {
        $options = is_array($all[VeryLongSentence::HANDLE] ?? null) ? $all[VeryLongSentence::HANDLE] : [];

        if (($options['enabled'] ?? true) === false) {
            return null;
        }

        return (new VeryLongSentence($options, default: 2 * self::longThreshold($all)))->words();
    }

    /**
     * @param  array<string, mixed>  $config  the `rules` section: handle => options
     * @param  array<string, callable(array<string, mixed>, array<string, mixed>): Rule>|null  $registry  the rules to choose from; the package's own when null
     */
    public static function fromConfig(array $config, ?array $registry = null): self
    {
        $registry ??= self::registry();

        $unknown = array_diff(array_keys($config), array_keys($registry));

        if ($unknown !== []) {
            throw new InvalidArgumentException(sprintf(
                'No rule is called "%s". The rules are: %s.',
                implode('", "', array_map(strval(...), $unknown)),
                implode(', ', array_keys($registry)) ?: '(none)',
            ));
        }

        $rules = [];

        foreach ($registry as $handle => $make) {
            $options = is_array($config[$handle] ?? null) ? $config[$handle] : [];

            if (($options['enabled'] ?? true) === false) {
                continue;
            }

            $rules[] = $make($options, $config);
        }

        return new self($rules);
    }

    public function check(Document $document): Findings
    {
        $findings = [];

        foreach ($this->rules as $rule) {
            array_push($findings, ...$rule->check($document));
        }

        return new Findings($findings);
    }

    /**
     * @return list<Rule>
     */
    public function rules(): array
    {
        return $this->rules;
    }

    /**
     * @return list<string>
     */
    public function handles(): array
    {
        return array_map(static fn (Rule $rule): string => $rule->handle(), $this->rules);
    }

    public function without(string ...$handles): self
    {
        return new self(array_values(array_filter($this->rules, static fn (Rule $rule): bool => ! in_array($rule->handle(), $handles, true))));
    }

    public function with(Rule ...$rules): self
    {
        return new self([...$this->rules, ...array_values($rules)]);
    }
}
