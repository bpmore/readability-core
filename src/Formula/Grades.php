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
