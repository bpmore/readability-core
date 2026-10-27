# Changelog

What changed in each release, and what you have to do about it.

Versions are `MAJOR.MINOR.PATCH`. Before 1.0 a breaking change raises the minor.

## 0.2.0 - Unreleased

Nothing to do on upgrade. Nothing that read the engine before reads anything
different now.

- **What the author wrote, and where a finding is in it.** Every block an
  extractor makes keeps `written`, its text before any cleaning, and
  `writtenSpan()`, which turns a position in the cleaned text into one in
  `written`. The map is kept through normalising and through the
  `Addresses` and `References` exclusions. For Bard, `written` includes
  inline code the rules skip, so it matches the paragraph in the editor.
- **The cleaning is unchanged.** `Normalizer` and the exclusions' tidying
  now run on `Text\Tracked`, which carries the map; tests hold their output
  to copies of the old code on thousands of generated strings, and every
  existing test passes unchanged.
- **Its own tests on GitHub.** The suite runs on PHP 8.2 with the oldest
  dependencies allowed and on PHP 8.4 with the newest.

## 0.1.0 - 2026-09-14

The first release: extraction from HTML, Bard and plain text; the exclusion
pipeline; English sentence segmentation and syllable counting; Flesch-Kincaid,
Gunning Fog, SMOG and Coleman-Liau with reading ease, reported as a band;
per-section grades; the locale guard; and the eight plain-language rules
with plainlanguage.gov's starter dictionary.
