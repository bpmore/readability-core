<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Formula;

/**
 * The reading level a site is writing for, as a band: a grade and how
 * far either side of it is fine. Grade 8 with a tolerance of 1 is 7–9,
 * which is where most plain-language guidance for the public lands.
 *
 * The verdict is made from the band that is shown, never from the
 * decimal behind it, so a person never reads "Grade 9–10, above your
 * target of 7–9" and wonders how.
 */
final class Target
{
    public function __construct(public readonly Band $band) {}

    public static function grade(int $grade, int $tolerance = 1): self
    {
        return new self(Band::around($grade, $tolerance));
    }

    /**
     * From the `target` section of a site's config: `['grade' => 8, 'tolerance' => 1]`.
     *
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(array $config): self
    {
        return self::grade((int) ($config['grade'] ?? 8), (int) ($config['tolerance'] ?? 1));
    }

    public function compare(Band $band): Comparison
    {
        return $band->compareTo($this->band);
    }
}
