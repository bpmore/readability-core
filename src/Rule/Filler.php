<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Rule;

use Bpmore\ReadabilityCore\Text\Document;

/**
 * Words that add nothing, and words that dodge. Tips, because "very" is
 * not a mistake; it is just a word the sentence is better without, and
 * "in some cases" is a way of not saying which cases.
 *
 * Two lists. Filler ("basically", "it is important to note that") gets
 * "cut it". A hedge ("perhaps", "tends to") gets "say it plainly, or say
 * how likely it is", because the fix for a hedge is a fact.
 *
 * Neither list holds anything the starter dictionary holds ("in order
 * to", "utilize", "prior to" are jargon, at warning), so an author never
 * gets a warning and a tip for the same words. A test keeps it so.
 */
final class Filler extends ConfiguredRule
{
    public const HANDLE = 'filler';

    /** @var list<string> */
    public const FILLER = [
        'actually', 'basically', 'really', 'very', 'quite', 'literally', 'simply', 'just', 'in fact', 'as a matter of fact',
        'it is important to note that', 'it should be noted that', 'it is worth noting that', 'please note that', 'note that',
        'needless to say', 'for all intents and purposes', 'at the end of the day', 'the fact that', 'in terms of',
        'it goes without saying', 'as you may know', 'as you are aware', 'as you know', 'in my opinion', 'i think that',
        'we believe that', 'there is no doubt that', 'each and every', 'first and foremost', 'in the process of',
        'on a daily basis', 'a total of', 'the majority of', 'in a timely manner', 'take into consideration',
        'in close proximity to', 'in the vicinity of', 'in spite of the fact that', 'despite the fact that',
        'owing to the fact that', 'with the possible exception of', 'in the final analysis', 'at all times',
    ];

    /** @var list<string> */
    public const HEDGES = [
        'perhaps', 'maybe', 'possibly', 'arguably', 'seemingly', 'apparently', 'presumably', 'somewhat', 'fairly',
        'relatively', 'virtually', 'essentially', 'it seems', 'it seems that', 'it appears', 'it appears that',
        'in some cases', 'in many cases', 'in most cases', 'to some extent', 'to a certain extent', 'more or less',
        'generally speaking', 'may or may not', 'could potentially', 'it is possible that', 'it may be that',
        'tends to', 'tend to', 'kind of', 'sort of', 'a bit', 'a little bit', 'for the most part', 'as far as we know',
    ];

    /** @var list<array{string, string}> pattern, kind */
    private readonly array $patterns;

    /**
     * @param  array<string, mixed>  $options  `ignore` (words or phrases a site allows), `severity`
     */
    public function __construct(array $options = [])
    {
        parent::__construct($options);

        $ignore = array_map(strtolower(...), array_filter((array) $this->option('ignore', []), is_string(...)));
        $terms = [];

        foreach ([['filler', self::FILLER], ['hedge', self::HEDGES]] as [$kind, $list]) {
            foreach ($list as $term) {
                if (! in_array(strtolower($term), $ignore, true)) {
                    $terms[] = [$term, $kind];
                }
            }
        }

        usort($terms, static fn (array $a, array $b): int => strlen($b[0]) <=> strlen($a[0]));

        $this->patterns = array_map(static fn (array $t): array => [Phrases::pattern($t[0]), $t[1]], $terms);
    }

    public function handle(): string
    {
        return self::HANDLE;
    }

    protected function defaultSeverity(): Severity
    {
        return Severity::Tip;
    }

    public function check(Document $document): array
    {
        $findings = [];

        foreach ($document->blocks as $index => $block) {
            foreach (Phrases::findAll($block->text, $this->patterns) as [$offset, $written, $kind]) {
                $findings[] = $this->finding(
                    $kind === 'hedge'
                        ? sprintf('"%s" hedges. Say it plainly, or say how likely it is.', $written)
                        : sprintf('"%s" adds nothing. Cut it.', $written),
                    new Location($index, $offset, strlen($written)),
                    $written,
                );
            }
        }

        return $findings;
    }
}
