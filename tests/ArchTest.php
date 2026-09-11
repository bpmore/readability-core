<?php

declare(strict_types=1);

use Composer\Autoload\ClassLoader;

// The boundary this package exists to keep: nothing in here knows about
// Statamic or Laravel, so one engine can serve Plain, A11y Report, and
// whatever comes next without dragging a framework in behind it.
//
// Two tests for the one rule, because the obvious one is blind here. Pest's
// `toUse()` only counts a reference to a class that exists, and in this
// suite nothing from Statamic or Illuminate is installed, so a stray
// `use Illuminate\Support\Str` is invisible to it. That was tried, and the
// rule stayed green. It is kept because it comes alive wherever the
// framework is present, which is the addon's suite. The token scan below is
// what enforces the boundary standalone.
arch('core stays framework-agnostic')
    ->expect('Bpmore\ReadabilityCore')
    ->not->toUse(['Statamic', 'Illuminate']);

arch('every source file declares strict types')
    ->expect('Bpmore\ReadabilityCore')
    ->toUseStrictTypes();

it('references neither Statamic nor Illuminate anywhere in src', function () {
    $src = realpath(__DIR__.'/../src');
    $files = new RegexIterator(
        new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS)),
        '/\.php$/',
    );

    $hits = [];

    foreach ($files as $file) {
        foreach (PhpToken::tokenize((string) file_get_contents($file->getPathname())) as $token) {
            // Comments and strings may say "Statamic"; only code may not. A
            // comment is ignorable and a string token still wears its quotes,
            // so anchoring on the first character leaves both alone.
            if ($token->isIgnorable()) {
                continue;
            }

            if (preg_match('/^\\\\?(Statamic|Illuminate)(\\\\|$)/', $token->text) === 1) {
                $hits[] = substr($file->getPathname(), strlen($src) + 1).':'.$token->line.' '.$token->text;
            }
        }
    }

    expect($hits)->toBe([]);
});

it('is autoloaded by Composer from src under its own namespace', function () {
    /** @var ClassLoader $loader */
    $loader = require __DIR__.'/../vendor/autoload.php';

    $directories = array_map(realpath(...), $loader->getPrefixesPsr4()['Bpmore\\ReadabilityCore\\'] ?? []);

    expect($directories)->toBe([realpath(__DIR__.'/../src')]);
});
