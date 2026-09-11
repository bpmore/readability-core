<?php

declare(strict_types=1);

use Bpmore\ReadabilityCore\Exclusion\Tidy;

it('closes the gaps a removed span leaves, taking its comma with it', function () {
    expect(Tidy::text('I met '.Tidy::GAP.' at '.Tidy::GAP.'.'))->toBe('I met at.');
    expect(Tidy::text('at '.Tidy::GAP.', '.Tidy::GAP.'. Call '.Tidy::GAP.' or '.Tidy::GAP.' to book.'))->toBe('at. Call or to book.');
    expect(Tidy::text('(and '.Tidy::GAP.').'))->toBe('(and).');
});

it('drops empty brackets, doubled commas, a comma before a period, and a line with nothing left in it', function () {
    expect(Tidy::text('We saw () them , , and [ ] left ,.'))->toBe('We saw them, and left.');
    expect(Tidy::text("Keep\n, .\n- -\nAlso keep"))->toBe("Keep\nAlso keep");
    expect(Tidy::text(', who came late'))->toBe('who came late');
});
