<?php

declare(strict_types=1);

use Bpmore\ReadabilityCore\Text\Words;

it('splits on whitespace and takes the punctuation off each word', function () {
    expect(Words::of('Walk-ins are welcome, e.g. "today" — (see 2021)!'))->toBe(['Walk-ins', 'are', 'welcome', 'e.g', 'today', 'see', '2021']);
});

it('counts a token with no letter or digit as no word at all', function () {
    expect(Words::of('— ... !? "" 5% $4.50'))->toBe(['5', '4.50']);
    expect(Words::of(''))->toBe([]);
    expect(Words::of("  \n  "))->toBe([]);
});

it('keeps multibyte letters', function () {
    expect(Words::of('Crème brûlée, naïve'))->toBe(['Crème', 'brûlée', 'naïve']);
});

it('counts letters and digits as characters, and nothing else', function () {
    expect(Words::characters('e.g'))->toBe(2);
    expect(Words::characters('COVID-19'))->toBe(7);
    expect(Words::characters("doctor's"))->toBe(7);
    expect(Words::characters('brûlée'))->toBe(6);
});
