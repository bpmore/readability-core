<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Exclusion;

use Bpmore\ReadabilityCore\Text\Block;
use Bpmore\ReadabilityCore\Text\Document;

/**
 * Reference lists, citations and bibliographies. Author names, journal
 * titles, volume numbers and DOIs will put any page at graduate level,
 * and none of it is what the page is asking the reader to understand.
 *
 * Three ways to find them, because pages mark them three ways:
 *
 * - A heading that says so ("References", "Sources", "Further reading")
 *   starts a section that runs to the next heading of the same or a
 *   higher level, or to the end of the element the heading was in,
 *   whichever comes first. All of it goes. The second bound is for a whole
 *   page, where the footer comes after the last section and is not a
 *   reference.
 * - Markup that says so: a class or id with "references" or "footnotes"
 *   in it, or the DPUB roles a careful site uses (`doc-endnotes`,
 *   `doc-bibliography`).
 * - A block that reads like a citation: a DOI, "et al.", an ISBN, a
 *   "Retrieved from", a journal's "2020;12(3):45". One such mark drops a
 *   list item; a paragraph needs two, because prose that mentions what
 *   Smith et al. found is still prose.
 *
 * And inline, the marks a citation leaves in a sentence: "[1]" after a
 * word, "(Smith et al., 2019)" in the middle of one. Those are cut out and
 * the sentence stays.
 */
final class References implements Exclusion
{
    private const HEADINGS = '/^(?:references?|sources?|bibliography|citations?|notes|footnotes|endnotes|works cited|further reading|literature cited|reference list|see also|related reading|sources? and notes)\s*[:.]?$/i';

    private const MARKUP = '/references?|citations?|bibliograph|footnotes?|endnotes?/i';

    /** @var list<string> */
    private const ROLES = ['doc-endnotes', 'doc-endnote', 'doc-bibliography', 'doc-biblioentry', 'doc-footnote'];

    /** @var list<string> Each one is a mark a citation carries and prose does not. */
    private const CITATION = [
        '/\bdoi\s*:|doi\.org\//i',
        '/\bet al\b/i',
        '/\bISBN\b|\bPMID\b|\bPMCID\b/',
        '/\bRetrieved (?:from|on|\d)|\bAccessed (?:on |at |\d|[A-Z][a-z]+ \d)/',
        '/\b(?:vol|pp?|no)\.\s*\d/i',
        '/\b(?:19|20)\d{2}\s*;\s*\d+\s*(?:\(\d+\))?\s*:\s*\d+/',   // 2020;12(3):45
        '/\b[A-Z][a-z]+,\s+(?:[A-Z]\.\s?){1,3}(?:,|\s+(?:and|&))/', // Smith, J. A., and
    ];

    /** @var list<string> */
    private const INLINE = [
        '/\[\d{1,3}(?:\s*[,\x{2013}-]\s*\d{1,3})*\]/u',
        '/\(\s*[A-Z][\w\'-]+(?:\s+(?:and|&)\s+[A-Z][\w\'-]+)*(?:\s+et al\.?)?,?\s+(?:19|20)\d{2}[a-z]?(?:,\s*(?:p|pp)\.?\s*\d+(?:[\x{2013}-]\d+)?)?(?:\s*;\s*[^)]+)?\)/u',
    ];

    public function apply(Document $document): Document
    {
        $kept = [];
        $section = null;

        foreach ($document->blocks as $block) {
            if ($section !== null && ($this->leaves($block, $section['ancestors']) || ($block->isHeading() && $block->level <= $section['level']))) {
                $section = null;
            }

            if ($section === null && $block->isHeading() && preg_match(self::HEADINGS, $block->text) === 1) {
                $section = ['level' => $block->level, 'ancestors' => $block->origin->ancestors];
            }

            if ($section !== null || $this->isMarked($block) || $this->isCitation($block)) {
                continue;
            }

            $text = Tidy::text((string) preg_replace(self::INLINE, Tidy::GAP, $block->text));

            if ($text !== '') {
                $kept[] = $text === $block->text ? $block : $block->withText($text);
            }
        }

        return new Document($kept);
    }

    /**
     * Whether a block is outside the element a section's heading was in.
     *
     * @param  list<string>  $ancestors
     */
    private function leaves(Block $block, array $ancestors): bool
    {
        return array_slice($block->origin->ancestors, 0, count($ancestors)) !== $ancestors;
    }

    private function isMarked(Block $block): bool
    {
        if (array_intersect(self::ROLES, $block->origin->roles) !== []) {
            return true;
        }

        return preg_match(self::MARKUP, implode(' ', [...$block->origin->classes, ...$block->origin->ids])) === 1;
    }

    private function isCitation(Block $block): bool
    {
        $marks = count(array_filter(self::CITATION, static fn (string $pattern): bool => preg_match($pattern, $block->text) === 1));

        return $marks >= ($block->isListItem() ? 1 : 2);
    }
}
