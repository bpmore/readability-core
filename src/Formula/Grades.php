<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Formula;

/**
 * Every formula's answer for one set of counts, unrounded. The band that
 * is shown is made from these; these themselves go behind the details
 * toggle.
 */
final class Grades
{
    public function __construct(
        public readonly float $fleschKincaid,
        public readonly float $gunningFog,
        public readonly float $smog,
        public readonly float $colemanLiau,
        /** Not a grade: higher is easier, 60–70 is plain English. */
        public readonly float $readingEase,
    ) {}

    /** Null when there is nothing to grade. */
    public static function of(Counts $counts): ?self
    {
        if ($counts->isEmpty()) {
            return null;
        }

        return new self(
            (new FleschKincaid)->grade($counts),
            (new GunningFog)->grade($counts),
            (new Smog)->grade($counts),
            (new ColemanLiau)->grade($counts),
            (new FleschReadingEase)->score($counts),
        );
    }

    /**
     * The grade the four formulas agree on: their median. A median rather
     * than a mean because SMOG was normed on thirty-sentence samples and
     * wanders on short text, and one formula off on its own should not
     * move the answer.
     *
     * A decimal, and so not for showing. It is here for the band, for
     * ordering entries by difficulty, and for the details view.
     */
    public function consensus(): float
    {
        $grades = [$this->fleschKincaid, $this->gunningFog, $this->smog, $this->colemanLiau];
        sort($grades);

        return ($grades[1] + $grades[2]) / 2;
    }

    /** The band to show. */
    public function band(): Band
    {
        return Band::of($this->consensus());
    }

    /**
     * The four grades by formula name.
     *
     * @return array<string, float>
     */
    public function byFormula(): array
    {
        return [
            (new FleschKincaid)->name() => $this->fleschKincaid,
            (new GunningFog)->name() => $this->gunningFog,
            (new Smog)->name() => $this->smog,
            (new ColemanLiau)->name() => $this->colemanLiau,
        ];
    }
}
