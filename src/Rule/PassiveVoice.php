<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Rule;

use Bpmore\ReadabilityCore\Text\Document;
use Bpmore\ReadabilityCore\Text\EnglishSegmenter;
use Bpmore\ReadabilityCore\Text\Segmenter;
use Bpmore\ReadabilityCore\Text\Words;

/**
 * Passive voice, as a share of the document and never one sentence at a
 * time. Some passive is correct English; "The clinic was founded in
 * 1879" is fine, and a panel that flags it teaches authors to stop
 * reading the panel. What is worth saying is that a third of a page
 * hides who does what. So: one finding, when the share of passive
 * sentences passes the site's limit, with the sentences attached for
 * highlighting.
 *
 * A passive sentence, here, has a form of "to be", perhaps an adverb or
 * two, and then a past participle: "was reviewed", "is being treated",
 * "have not been told". The participle is a word ending in "-ed" or one
 * of the irregular ones. A few "-ed" words that are adjectives after
 * "to be" ("tired", "interested") are left alone; the rest of the false
 * positives are what the rate is for.
 */
final class PassiveVoice extends ConfiguredRule
{
    public const HANDLE = 'passive_voice';

    public const DEFAULT_MAX_PERCENT = 20;

    /** A rate on fewer sentences than this says nothing. */
    public const DEFAULT_MIN_SENTENCES = 5;

    /** @var list<string> */
    private const BE = ['am', 'is', 'are', 'was', 'were', 'be', 'been', 'being'];

    /** @var list<string> Words that may sit between "to be" and the participle. */
    private const BETWEEN = ['not', 'being', 'been', 'also', 'often', 'usually', 'always', 'never', 'still', 'already', 'now', 'then', 'all', 'both', 'each', 'just', 'only', 'even', 'well', 'so', 'too', 'very', 'ever', 'once', 'since', 'later', 'first', 'last', 'again'];

    /** @var list<string> Participles that do not end in "-ed". */
    private const IRREGULAR = [
        'begun', 'bitten', 'blown', 'born', 'borne', 'bought', 'bound', 'bred', 'broken', 'brought', 'built', 'burnt', 'cast',
        'caught', 'chosen', 'come', 'cost', 'cut', 'dealt', 'done', 'drawn', 'dreamt', 'driven', 'drunk', 'dug', 'eaten',
        'fallen', 'fed', 'felt', 'fled', 'flown', 'forbidden', 'forgiven', 'forgotten', 'found', 'frozen', 'given', 'gone',
        'gotten', 'ground', 'grown', 'heard', 'held', 'hidden', 'hit', 'hung', 'hurt', 'kept', 'known', 'laid', 'lain',
        'learnt', 'led', 'left', 'lent', 'let', 'lit', 'lost', 'made', 'meant', 'met', 'mistaken', 'overcome', 'overdone',
        'overseen', 'overtaken', 'paid', 'proven', 'put', 'read', 'rebuilt', 'redone', 'ridden', 'risen', 'run', 'said',
        'seen', 'sent', 'set', 'sewn', 'shaken', 'shot', 'shown', 'shut', 'slid', 'sold', 'sought', 'sown', 'spent', 'split',
        'spoken', 'spread', 'spun', 'stolen', 'stuck', 'struck', 'sung', 'sunk', 'swept', 'sworn', 'taken', 'taught', 'thought',
        'thrown', 'told', 'torn', 'trodden', 'understood', 'undertaken', 'undone', 'upset', 'withdrawn', 'withheld', 'woken',
        'won', 'worn', 'woven', 'written', 'wrung',
    ];

    /** @var list<string> Words in "-ed" that are adjectives after "to be", not verbs someone is doing to someone. */
    private const ADJECTIVES = [
        'tired', 'excited', 'interested', 'worried', 'bored', 'scared', 'pleased', 'surprised', 'delighted', 'disappointed',
        'satisfied', 'dedicated', 'committed', 'experienced', 'qualified', 'married', 'retired', 'relieved', 'confused',
        'frightened', 'amazed', 'annoyed', 'exhausted', 'embarrassed', 'thrilled', 'stressed', 'supposed', 'aged', 'unrelated',
    ];

    private readonly Segmenter $segmenter;

    /**
     * @param  array<string, mixed>  $options  `max_percent`, `min_sentences`, `severity`
     */
    public function __construct(array $options = [], ?Segmenter $segmenter = null)
    {
        parent::__construct($options);
        $this->segmenter = $segmenter ?? new EnglishSegmenter;
    }

    public function handle(): string
    {
        return self::HANDLE;
    }

    protected function defaultSeverity(): Severity
    {
        return Severity::Warning;
    }

    public function maxPercent(): int
    {
        return $this->intOption('max_percent', self::DEFAULT_MAX_PERCENT);
    }

    public function minSentences(): int
    {
        return $this->intOption('min_sentences', self::DEFAULT_MIN_SENTENCES);
    }

    public function check(Document $document): array
    {
        $total = 0;
        $passive = [];
        $example = null;

        foreach (Sentences::of($document, $this->segmenter) as [$block, $sentence]) {
            $total++;

            if (self::isPassive($sentence->text)) {
                $passive[] = Sentences::location($block, $sentence);
                $example ??= $sentence->text;
            }
        }

        if ($total < $this->minSentences() || $passive === []) {
            return [];
        }

        $percent = 100 * count($passive) / $total;

        if ($percent <= $this->maxPercent()) {
            return [];
        }

        return [$this->finding(
            sprintf(
                'About %d%% of the sentences here are in the passive voice: %d of %d. Keep it under %d%%. '
                .'Put the doer first where you can: "Your doctor will review the results", not "The results will be reviewed".',
                (int) round($percent),
                count($passive),
                $total,
                $this->maxPercent(),
            ),
            null,
            (string) $example,
            spans: $passive,
        )];
    }

    /**
     * Whether a sentence has a passive construction in it.
     */
    public static function isPassive(string $sentence): bool
    {
        $words = array_map(strtolower(...), Words::of($sentence));
        $count = count($words);

        for ($i = 0; $i < $count - 1; $i++) {
            if (! in_array($words[$i], self::BE, true)) {
                continue;
            }

            // Past "to be", step over what may sit between, then look for
            // the participle. Three steps at most: "has not yet been told".
            for ($j = $i + 1, $steps = 0; $j < $count && $steps < 3; $j++, $steps++) {
                if (self::isParticiple($words[$j])) {
                    return true;
                }

                if (! in_array($words[$j], self::BETWEEN, true) && ! str_ends_with($words[$j], 'ly')) {
                    break;
                }
            }
        }

        return false;
    }

    private static function isParticiple(string $word): bool
    {
        if (in_array($word, self::IRREGULAR, true)) {
            return true;
        }

        return strlen($word) >= 4 && str_ends_with($word, 'ed') && ! in_array($word, self::ADJECTIVES, true);
    }
}
