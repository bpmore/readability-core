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

PHP 8.2 or later with `dom`, `libxml` and `mbstring`, which every PHP has.
`intl` is optional: with it, accented characters are composed before they
are counted.

## Extraction

Every analysis starts from a `Document`: the blocks of text a reader would
read, in order, each knowing where it came from.

```php
use Bpmore\ReadabilityCore\Extraction\HtmlExtractor;

$document = (new HtmlExtractor)->extract($html);

foreach ($document as $block) {
    $block->kind;          // Heading, Paragraph, ListItem, Quote, Code, Cell, Caption or Other
    $block->text;          // normalised; a newline in it is a hard break the author made
    $block->level;         // 1 to 6 for a heading
    $block->origin->tag;   // 'p', 'h2', 'li', ... Bard node types are mapped onto these
    $block->isQuoted();    // inside a blockquote, whichever extractor produced it
}
```

One extractor per shape a field value has:

- `HtmlExtractor` — a rendered field, a rendered markdown field, or a whole
  page. Scripts, styles, media, form controls and hidden content are dropped.
- `BardExtractor` — the ProseMirror node tree Statamic stores, as the array
  or its JSON. Sets are walked (a disabled set is skipped), the set handle
  and node path are recorded on every block, and the Bard fields nested in a
  set are read. A set's plain string fields are not: the host has the
  blueprint and knows which are prose, and can hand those to the plain
  text extractor itself.
- `PlainTextExtractor` — a textarea or text field. A blank line separates
  paragraphs.

Markdown is not a shape: render it with the host's own parser and hand the
HTML to `HtmlExtractor`, so the text scored is the text published.

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
