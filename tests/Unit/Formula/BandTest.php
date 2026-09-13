<?php

declare(strict_types=1);

use Bpmore\ReadabilityCore\Formula\Band;
use Bpmore\ReadabilityCore\Formula\Comparison;
use Bpmore\ReadabilityCore\Formula\Grades;
use Bpmore\ReadabilityCore\Formula\Target;

it('puts an estimate in the whole-grade band it falls in', function () {
    expect(Band::of(9.0)->label())->toBe('Grade 9–10');
    expect(Band::of(9.4)->label())->toBe('Grade 9–10');
    expect(Band::of(9.99)->label())->toBe('Grade 9–10');
    expect(Band::of(10.0)->label())->toBe('Grade 10–11');
    expect(Band::of(7.99))->toEqual(new Band(7, 8));
});

it('has a floor of grade 1 and an open band at the top, because the formulas stop meaning anything past a degree', function () {
    expect(Band::of(-1.3)->label())->toBe('Grade 1–2');
    expect(Band::of(0.4)->label())->toBe('Grade 1–2');
    expect(Band::of(16.9)->label())->toBe('Grade 16–17');
    expect(Band::of(17.0)->label())->toBe('Grade 17+');
    expect(Band::of(29.8))->toEqual(new Band(17, null));
    expect(Band::of(29.8)->isOpen())->toBeTrue();
    expect(Band::of(9.4)->isOpen())->toBeFalse();
});

it('makes a target band around a grade, and reads a tolerance as a distance', function () {
    expect(Band::around(8, 1))->toEqual(new Band(7, 9));
    expect(Band::around(8, 0))->toEqual(new Band(8, 8));
    expect(Band::around(8, 0)->label())->toBe('Grade 8');
    expect(Band::around(1, 2))->toEqual(new Band(1, 3));   // never below grade 1
    expect(Band::around(8, -1))->toEqual(Band::around(8, 1));
});

it('compares to a target by the band alone, so the verdict never contradicts what is shown', function () {
    $target = Band::around(8, 1);   // 7–9

    expect(Band::of(5.5)->compareTo($target))->toBe(Comparison::Below);
    expect(Band::of(6.9)->compareTo($target))->toBe(Comparison::OnTarget);   // 6–7 touches 7
    expect(Band::of(7.2)->compareTo($target))->toBe(Comparison::OnTarget);
    expect(Band::of(9.99)->compareTo($target))->toBe(Comparison::OnTarget);  // 9–10 touches 9
    expect(Band::of(10.0)->compareTo($target))->toBe(Comparison::Above);
    expect(Band::of(17.5)->compareTo($target))->toBe(Comparison::Above);
    expect(Band::of(17.5)->compareTo(Band::of(17.5)))->toBe(Comparison::OnTarget);
    expect(Band::of(3.0)->compareTo(Band::of(17.5)))->toBe(Comparison::Below);
});

it('knows above and below as whole-band relations', function () {
    expect(Band::of(10.0)->isAbove(Band::around(8, 1)))->toBeTrue();
    expect(Band::of(9.0)->isAbove(Band::around(8, 1)))->toBeFalse();
    expect(Band::of(5.0)->isBelow(Band::around(8, 1)))->toBeTrue();
    expect(Band::of(6.0)->isBelow(Band::around(8, 1)))->toBeFalse();
    expect(Band::of(20.0)->isBelow(Band::of(20.0)))->toBeFalse();
    expect(Band::of(9.4)->equals(new Band(9, 10)))->toBeTrue();
    expect(Band::of(9.4)->equals(new Band(9, 11)))->toBeFalse();
});

it('reads a target from the config sketch, with grade 8 and a tolerance of 1 as the defaults', function () {
    expect(Target::fromConfig(['grade' => 8, 'tolerance' => 1])->band)->toEqual(new Band(7, 9));
    expect(Target::fromConfig(['grade' => 6, 'tolerance' => 2])->band)->toEqual(new Band(4, 8));
    expect(Target::fromConfig([])->band)->toEqual(new Band(7, 9));
    expect(Target::grade(10)->band)->toEqual(new Band(9, 11));
    expect(Target::grade(8)->compare(Band::of(11.2)))->toBe(Comparison::Above);
});

it('takes the median of the four formulas as the consensus, so one formula off on its own cannot move it', function () {
    // SMOG at 3.1 is its floor on a one-sentence text; the median ignores it.
    $grades = new Grades(7.6, 6.4, 3.1, 7.3, 69.0);

    expect($grades->consensus())->toEqualWithDelta(6.85, 0.0001);
    expect($grades->band()->label())->toBe('Grade 6–7');

    // Symmetric: the order the grades come in does not matter.
    expect((new Grades(3.1, 7.3, 7.6, 6.4, 69.0))->consensus())->toEqualWithDelta(6.85, 0.0001);
});
