<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Formula;

use Bpmore\ReadabilityCore\Exclusion\Exclusion;
use Bpmore\ReadabilityCore\Text\Document;

/**
 * A document cut into sections at its headings, each graded on its own.
 *
 * Every heading, of any level, starts a section; what comes before the
 * first is the lead. Chunking is by block kind, so it is the same for
 * HTML, Bard and rendered markdown, which all arrive as the same blocks.
 *
 * Cut from the document the rules see, names in, so a section's block
 * indexes are the ones findings carry. The grade wants names out, so the
 * exclusion for grading is applied to each section's blocks on their own
 * rather than to the document first, where dropping a block that was only
 * a name would shift every index after it.
 */
final class Sections
{
    /**
     * @param  Document  $document  the document as the rules see it
     * @param  ?Exclusion  $beforeGrading  what to take out before counting: ProperNames, or a pipeline of it
     * @return list<Section>
     */
    public static function of(Document $document, Tally $tally, ?Exclusion $beforeGrading = null): array
    {
        $sections = [];
        $heading = null;
        $indexes = [];
        $blocks = [];

        $cut = static function () use (&$sections, &$heading, &$indexes, &$blocks, $tally, $beforeGrading): void {
            if ($heading === null && $blocks === []) {
                return;
            }

            $graded = new Document($blocks);
            $sections[] = new Section($heading, $indexes, $blocks, $tally->of($beforeGrading?->apply($graded) ?? $graded));
        };

        foreach ($document->blocks as $index => $block) {
            if ($block->isHeading()) {
                $cut();
                $heading = $block;
                $indexes = [];
                $blocks = [];
            }

            $indexes[] = $index;
            $blocks[] = $block;
        }

        $cut();

        return $sections;
    }

    /**
     * The graded sections, hardest first: the worst-offenders view.
     *
     * @param  list<Section>  $sections
     * @return list<Section>
     */
    public static function hardestFirst(array $sections): array
    {
        $graded = array_values(array_filter($sections, static fn (Section $s): bool => ! $s->isEmpty()));
        usort($graded, static fn (Section $a, Section $b): int => $b->grades()->consensus() <=> $a->grades()->consensus());

        return $graded;
    }
}
