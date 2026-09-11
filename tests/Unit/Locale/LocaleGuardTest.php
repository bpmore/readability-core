<?php

declare(strict_types=1);

use Bpmore\ReadabilityCore\Locale\Decision;
use Bpmore\ReadabilityCore\Locale\LocaleGuard;

it('allows English, whatever the region, case or separator, and nothing else by default', function () {
    $guard = new LocaleGuard;

    foreach (['en', 'en_US', 'en-GB', 'EN_au', 'en_nz'] as $locale) {
        expect($guard->allows($locale))->toBeTrue($locale);
    }

    foreach (['fr', 'de_DE', 'pt-BR', 'es', 'zz'] as $locale) {
        expect($guard->allows($locale))->toBeFalse($locale);
    }
});

it('reads the locales setting: a bare language allows every region of it, a full locale allows only itself', function () {
    $guard = LocaleGuard::fromConfig(['locales' => ['en_US', 'fr']]);

    expect($guard->allows('en_US'))->toBeTrue();
    expect($guard->allows('en-us'))->toBeTrue();
    expect($guard->allows('en_GB'))->toBeFalse();
    expect($guard->allows('en'))->toBeFalse();
    expect($guard->allows('fr_CA'))->toBeTrue();
});

it('falls back to English alone when the setting is missing or empty', function () {
    expect(LocaleGuard::fromConfig([])->allows('en_GB'))->toBeTrue();
    expect(LocaleGuard::fromConfig(['locales' => []])->allows('en_GB'))->toBeTrue();
    expect(LocaleGuard::fromConfig(['locales' => [null, 42]])->allows('en_GB'))->toBeTrue();
    expect(LocaleGuard::fromConfig(['locales' => ['']])->allows('fr'))->toBeFalse();
});

it('refuses another language with a message that says why and what to do', function () {
    $decision = (new LocaleGuard)->check('de_DE');

    expect($decision)->toBeInstanceOf(Decision::class);
    expect($decision->allowed)->toBeFalse();
    expect($decision->locale)->toBe('de_DE');
    expect($decision->language)->toBe('de');
    expect($decision->message())
        ->toContain('not checked')
        ->toContain('built on English')
        ->toContain('add de_DE to the locales setting')
        ->toContain(class_exists(Locale::class) ? 'German (de_DE)' : 'set to de_DE');
});

it('refuses a site with no language set, rather than guessing', function () {
    foreach (['', '  ', 'default', 'DEFAULT'] as $locale) {
        $decision = (new LocaleGuard)->check($locale);

        expect($decision->allowed)->toBeFalse($locale);
        expect($decision->language)->toBe('');
        expect($decision->message())->toContain('no language set')->toContain("Set the site's locale");
    }
});

it('says nothing when it allows', function () {
    $decision = (new LocaleGuard)->check('en-GB');

    expect($decision->allowed)->toBeTrue();
    expect($decision->message())->toBeNull();
    expect($decision->locale)->toBe('en-GB');
    expect($decision->language)->toBe('en');
});

it('does not pretend to know a language it cannot name', function () {
    expect((new LocaleGuard)->check('zz')->message())->toContain('set to zz')->not->toContain('zz (zz)');
});
