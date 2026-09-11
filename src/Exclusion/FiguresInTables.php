<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Exclusion;

use Bpmore\ReadabilityCore\Text\Block;
use Bpmore\ReadabilityCore\Text\Document;

/**
 * Tables of figures. A cell that holds "12,400" is not a one-word sentence,
 * and a table with a column of them is not prose, whatever its headers say.
 *
 * A cell is a figure when no word of three or more letters is in it: a
 * number, a currency amount, a percentage, a dash, "N/A", "5 mg". A run of
 * cells is one table, and when a third of its cells are figures the whole
 * table goes, headers and labels with it. Below that only the figures go,
 * because a comparison table written in sentences is prose with numbers in
 * it.
 */
final class FiguresInTables implements Exclusion
{
    public function apply(Document $document): Document
    {
        $kept = [];
        $table = [];

        foreach ([...$document->blocks, null] as $block) {
            if ($block instanceof Block && $block->isInTable()) {
                $table[] = $block;

                continue;
            }

            array_push($kept, ...$this->prune($table));
            $table = [];

            if ($block instanceof Block) {
                $kept[] = $block;
            }
        }

        return new Document($kept);
    }

    /**
     * @param  list<Block>  $table
     * @return list<Block>
     */
    private function prune(array $table): array
    {
        if ($table === []) {
            return [];
        }

        $figures = array_filter($table, static fn (Block $cell): bool => preg_match('/\p{L}{3,}/u', $cell->text) !== 1);

        if (count($figures) * 3 >= count($table)) {
            return [];
        }

        return array_values(array_diff_key($table, $figures));
    }
}
