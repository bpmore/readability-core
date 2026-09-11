# readability-core

Readability and plain-language analysis for English text. Framework-agnostic
PHP: it takes text in, and gives grades, rule hits and findings back. It knows
nothing about Statamic or Laravel, and a test keeps it that way.

It is the engine behind two products by the same author, which is the reason it
is its own package:

- **Plain** (`bpmore/statamic-plain`) — the publish-form panel, the opt-in
  gate, the jargon dictionary and the rest of the control panel surface.
- **A11y Report** — readability as a scan dimension, and the evidence toward
  WCAG SC 3.1.5 (a AAA criterion, reported separately from any AA claim).

A site with both installed runs one engine, not two.

## Requirements

PHP 8.2 or later. Nothing else at runtime.

## Using it from a consumer

Until it is published, point a Composer path repository at this directory:

```json
{
    "repositories": [
        { "type": "path", "url": "packages/readability-core" }
    ],
    "require": {
        "bpmore/readability-core": "@dev"
    }
}
```

## Working on it

Everything runs from this directory, with no host application:

```sh
composer install
vendor/bin/pest          # the suite
vendor/bin/pint --test   # formatting, check only; drop --test to fix
```

The suite includes `tests/ArchTest.php`, which fails the build if anything under
`src/` references `Statamic\` or `Illuminate\`. Note in that file the reason it
scans tokens rather than relying on Pest's `toUse()` alone.
