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

## English only, and it says so

Every formula here was calibrated on English. Run on German they still
return a number, and the number means nothing. So before anything is
analysed, the site's locale is checked against the `locales` setting,
and a site that is not on it gets no score and a message that says why.

```php
use Bpmore\ReadabilityCore\Locale\LocaleGuard;

$decision = LocaleGuard::fromConfig(['locales' => ['en', 'en_US', 'en_GB']])->check($siteLocale);

$decision->allowed;     // false for "fr"
$decision->message();   // "Readability is not checked on this site. Its language is French (fr), and ..."
```

A bare language on the list (`en`) allows every region of it; a full
locale (`en_US`) allows only itself. A site with no locale set is refused
too, with a message that says to set one: "unknown" is not a language the
formulas know either.

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
which other words the fix changed. Two such fixes have been made since the
port, each marked in the class where it was made: "-sed" past participles
("used", "closed", "diagnosed") no longer get a syllable they do not have,
and the "pro-" prefix no longer takes one from "prove", "proud" or "proof".
Both were found by running the published reference texts through the
whole chain, which the test suite does.

## Grades

The formulas are arithmetic over one set of tallies, so every grade is of
the same words and sentences.

```php
use Bpmore\ReadabilityCore\Formula\Grades;
use Bpmore\ReadabilityCore\Formula\Tally;

$counts = (new Tally(new EnglishSegmenter, new EnglishSyllableCounter))->of($forGrades);
$grades = Grades::of($counts);        // null when there is nothing to grade

$grades->fleschKincaid;               // years of schooling, unrounded
$grades->gunningFog;
$grades->smog;
$grades->colemanLiau;
$grades->readingEase;                 // not a grade: higher is easier
$grades->byFormula();                 // ['Flesch-Kincaid' => 6.0, ...]
```

`Tally` leaves headings out (they are section markers, not sentences),
counts list items (a bulleted list is a plain-language technique), treats
a number as a one-syllable word, and counts Gunning's complex words with
Gunning's exclusions: not compounds, and not words that reach three
syllables only by an "-es", "-ed" or "-ing".

## The band

What a person sees is a band, never a decimal. "Grade 9–10" says what the
formulas know; "9.4" says something they do not, and is the number people
argue with.

```php
use Bpmore\ReadabilityCore\Formula\Target;

$band   = $grades->band();                                    // Band(9, 10)
$target = Target::fromConfig(['grade' => 8, 'tolerance' => 1]); // 7–9

$band->label();                 // "Grade 9–10" — or "Grade 17+" at the top
$target->compare($band);        // Comparison::OnTarget | Above | Below
```

The band comes from the median of the four formulas, so one formula off
on its own cannot move it (SMOG wanders on short text). It is a whole
grade wide, floored at 1–2 and open at 17+. The verdict against the
target is made from the band, not from the decimal behind it, so what is
shown never contradicts what is said about it: a band that overlaps the
target is on target. Above is the verdict that matters; below is not a
problem and nothing should treat it as one.

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

## Rules

The grade says "harder than you meant"; a rule says which sentence and
what to do with it. One class per rule, each testable on its own with a
`Document` and nothing else.

```php
use Bpmore\ReadabilityCore\Rule\RuleSet;

$findings = RuleSet::fromConfig($config['rules'])->check($forRules);

foreach ($findings as $finding) {
    $finding->rule;        // 'long_sentence' — what a gate blocks on
    $finding->severity;    // Severity::Error | Warning | Tip
    $finding->message;     // what to do, in plain words
    $finding->location;    // block index, byte offset and length — or null for a finding about the whole document
    $finding->excerpt;     // the sentence or phrase, as written
    $finding->suggestion;  // something to say instead, when the rule has one
    $finding->spans;       // for a whole-document finding, the places that made it
}
```

`RuleSet::fromConfig()` takes the spec's `rules` section: each handle's
options, defaults for a rule the config does not mention, `'enabled' =>
false` to leave one out, and a loud refusal for a handle it has never
heard of. Findings come back in reading order, whole-document findings
last, and can be filtered by rule, severity or block.

| Rule | Finds | Default |
|---|---|---|
| `long_sentence` | A sentence over `words` (25). Stops where the next rule starts, so a sentence is never said twice. | warning |
| `very_long_sentence` | A sentence over `words` — twice the long limit unless set, so 50. What a gate may block on. | error |
| `long_paragraph` | A block over `words` (150) or over `sentences` (8); either alone. Every block but a heading. | warning |
| `passive_voice` | Passive sentences as a share of the document, over `max_percent` (20) — **one finding, never one per sentence**, with the sentences attached as `spans` for highlighting. Quiet under `min_sentences` (5). | warning |
