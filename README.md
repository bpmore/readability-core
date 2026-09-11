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
Nothing else at runtime. `intl` is optional: with it, accented characters
are composed before they are counted.

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

## Exclusions

Text that should not count is taken out before anything is scored: a score
of the wrong text is not an estimate, it is a different number.

```php
use Bpmore\ReadabilityCore\Exclusion\Code;
use Bpmore\ReadabilityCore\Exclusion\Pipeline;
use Bpmore\ReadabilityCore\Exclusion\ProperNames;

$exclude = ['html_classes' => ['no-readability'], 'bard_sets' => ['code', 'citation']];

$document = (new HtmlExtractor(ignoredTags: Code::INLINE_TAGS, ignoredClasses: $exclude['html_classes']))
    ->extract($html);

$pipeline  = Pipeline::fromConfig($exclude);
$forRules  = $pipeline->without(ProperNames::class)->apply($document);
$forGrades = $pipeline->apply($document);
```

One class per exclusion, each on unless the config turns it off:

| Exclusion         | Takes out                                                                     |
|-------------------|-------------------------------------------------------------------------------|
| `Code`            | Code blocks. Inline code needs the extractor: `Code::INLINE_TAGS` / `INLINE_MARKS`. |
| `Quotes`          | Block quotations, attribution included. The score is of the author.           |
| `References`      | A section under a "References"-type heading; blocks marked by class, id or DPUB role; blocks that read like a citation; and, inside a sentence, `[1]` and `(Smith et al., 2019)`. |
| `FiguresInTables` | A table where a third of the cells are figures, whole; otherwise just the figures. |
| `Addresses`       | Street lines, PO boxes, city-state-zip, UK and Canadian postcodes, phone numbers — cut out of the sentence, which stays. An `<address>` element whole. |
| `IgnoredClasses`  | Anything inside an element with one of the site's classes.                    |
| `IgnoredSets`     | Anything inside a Bard set with one of the site's handles.                    |
| `ProperNames`     | Names, honorifics and acronyms — SC 3.1.5 grades text "after removal of proper names and titles". **For the formulas only:** the rules want the names in. |

`ProperNames` has no dictionary and no tagger, only capitalisation, and it
errs toward leaving a word in: a lone capitalised word at the start of a
sentence stays, and so does every word of a title-case line. Its docblock
says exactly what goes and what does not.

## Sentences

Every formula divides by the number of sentences, so this is where a
score is most easily put wrong. `EnglishSegmenter` splits a block's text
by the rules of English punctuation and the abbreviations that break
them: "Dr. Smith went to St. Louis Inc. yesterday." is one sentence, and
"The meeting is at 3 p.m. Please be on time." is two.

```php
use Bpmore\ReadabilityCore\Text\EnglishSegmenter;

foreach ($document as $block) {
    foreach ((new EnglishSegmenter)->sentences($block->text) as $sentence) {
        $sentence->text;     // trimmed
        $sentence->offset;   // byte offset within the block's text
    }
}
```

A block boundary is always a sentence boundary, so segment block by
block; a hard break inside a block is one too. It was chosen over ICU's
sentence iterator and the Packagist options by measuring all of them
against the same traps; the test file carries the corpus and the scores.
`Segmenter` is an interface, so a better one can be dropped in.

## Syllables

Counting syllables is the hard part of every formula. `SyllableCounter`
is the interface; `EnglishSyllableCounter` is the implementation: the
rules of Dave Child's Text-Statistics (from Greg Fast's
Lingua::EN::Syllable), ported into this package rather than depended on,
because that library's last release was in 2018 and it calls a function
PHP has deprecated. The port matched it on every word of the system
dictionary before the dependency was dropped; the notice its licence
asks for is in `THIRD-PARTY-LICENSES.md`.

Two corrections happen before the rules: accents come off ("café" is not
"caf") and a vowelless initialism is read letter by letter ("CDC" is
three). The exception lists are in the class, so a word that comes up
wrong can be fixed in place — and the fidelity test will show exactly
which other words the fix changed.

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
