<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Formula;

/**
 * A readability formula that gives a US school grade: the years of
 * schooling a reader is assumed to need. Arithmetic over Counts, nothing
 * more, so the same tallies give every grade and the grades agree on
 * what was counted.
 */
interface Formula
{
    /** A short name for the details view: "Flesch-Kincaid". */
    public function name(): string;

    /**
     * The grade, unrounded. What is shown is a band, never this number;
     * it is here for the details toggle and for the tests.
     *
     * @throws \InvalidArgumentException when there is nothing to grade
     */
    public function grade(Counts $counts): float;
}
