<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Formula;

/**
 * A grade band: what is shown instead of a number.
 *
 * "Grade 9–10" says the reading level is somewhere in there, which is all
 * these formulas know. "9.4" says something they do not, and is the number
 * a person argues with. So the decimals stay behind a details toggle and
 * this is the headline.
 *
 * A band is a whole grade wide: an estimate anywhere from 9.0 to 9.99 is
 * "Grade 9–10". The bottom is "Grade 1–2", however easy the text; the top
 * is "Grade 17+", however hard, because past a degree the formulas stop
 * meaning anything.
 */
final class Band
{
    public const LOWEST = 1;

    /** From here on, the band has no upper grade. */
    public const OPEN = 17;

    public function __construct(
        public readonly int $low,
        /** Null for the open band at the top. */
        public readonly ?int $high,
    ) {}

    /** The band an unrounded grade falls in. */
    public static function of(float $grade): self
    {
        $low = max(self::LOWEST, min((int) floor($grade), self::OPEN));

        return new self($low, $low >= self::OPEN ? null : $low + 1);
    }

    /** A band around a grade: 8 with a tolerance of 1 is 7–9. A tolerance is a distance, so its sign is ignored. */
    public static function around(int $grade, int $tolerance): self
    {
        $tolerance = abs($tolerance);

        return new self(max(self::LOWEST, $grade - $tolerance), max(self::LOWEST, $grade + $tolerance));
    }

    /** "Grade 9–10", "Grade 17+", and "Grade 6" for a target with no tolerance. English; a host with translations formats from low and high itself. */
    public function label(): string
    {
        return match (true) {
            $this->high === null => 'Grade '.$this->low.'+',
            $this->high === $this->low => 'Grade '.$this->low,
            default => 'Grade '.$this->low.'–'.$this->high,
        };
    }

    public function isOpen(): bool
    {
        return $this->high === null;
    }

    /** Entirely harder than another band: nothing in this one is in that one or easier. */
    public function isAbove(self $other): bool
    {
        return $other->high !== null && $this->low > $other->high;
    }

    /** Entirely easier than another band. */
    public function isBelow(self $other): bool
    {
        return $this->high !== null && $this->high < $other->low;
    }

    public function compareTo(self $target): Comparison
    {
        return match (true) {
            $this->isAbove($target) => Comparison::Above,
            $this->isBelow($target) => Comparison::Below,
            default => Comparison::OnTarget,
        };
    }

    public function equals(self $other): bool
    {
        return $this->low === $other->low && $this->high === $other->high;
    }
}
