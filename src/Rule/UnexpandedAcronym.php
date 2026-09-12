<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Rule;

use Bpmore\ReadabilityCore\Text\Document;

/**
 * An acronym the page never spells out, or spells out too late. "Ask
 * about your HbA1c" means nothing to someone who has not been told what
 * that is, and the person writing it has long forgotten there was a
 * time they did not know.
 *
 * The plain-language convention is the test: write it in full the first
 * time, with the short form after it in brackets, and use the short form
 * from then on. So "Centers for Disease Control and Prevention (CDC)"
 * defines CDC, "CDC (Centers for ...)" does too, and so does a phrase
 * whose initials spell it, "World Health Organization" for WHO. A
 * definition after the first use is a finding of its own kind.
 *
 * One finding per acronym, at its first use. Headings are not scanned:
 * an acronym in a title that the first paragraph then defines is fine.
 * The universally known ("TV", "PDF", "ER") are never flagged, and a
 * site adds its own to `ignore`.
 */
final class UnexpandedAcronym extends ConfiguredRule
{
    public const HANDLE = 'unexpanded_acronym';

    /**
     * Short forms a reader of English knows without being told. Compared
     * without periods, so "U.S." is "US".
     *
     * @var list<string>
     */
    public const KNOWN = [
        'AM', 'PM', 'OK', 'ID', 'TV', 'PDF', 'URL', 'FAQ', 'PIN', 'ATM', 'US', 'USA', 'UK', 'EU', 'UN', 'NASA', 'FBI', 'IRS',
        'DIY', 'CEO', 'CV', 'PS', 'RSVP', 'VIP', 'ZIP', 'DNA', 'HIV', 'AIDS', 'CPR', 'ER', 'ICU', 'GP', 'IQ', 'BMI', 'COVID',
        'COVID-19', 'IT', 'AC', 'DC', 'HR', 'PR', 'PC', 'GPS', 'SMS', 'HTML', 'WIFI', 'DVD', 'CD', 'USB', 'AD', 'BC', 'BCE', 'CE',
        'MD', 'RN', 'DR', 'MR', 'MRS', 'MS', 'JR', 'SR', 'ETA', 'ASAP', 'TBD', 'NA', 'AKA', 'EG', 'IE', 'ETC', 'VS', 'ST', 'AVE',
    ];

    /** @var list<string> Words an expansion may hold that do not give it an initial. */
    private const SMALL = ['of', 'and', 'the', 'for', 'in', 'on', 'at', 'to', 'a', 'an', 'by', 'with', 'or', 'de', 'du', 'des', '&'];

    private const CANDIDATE = '/(?<![\p{L}\p{N}])([A-Z][A-Z0-9]*(?:[.\-][A-Z0-9]+)*)(?:\'s|s)?(?![\p{L}\p{N}])/u';

    /** @var list<string> */
    private readonly array $ignored;

    /**
     * @param  array<string, mixed>  $options  `ignore` (a list of acronyms never flagged, added to the known ones), `severity`
     */
    public function __construct(array $options = [])
    {
        parent::__construct($options);
        $this->ignored = array_map(self::normalize(...), [...self::KNOWN, ...array_filter((array) $this->option('ignore', []), is_string(...))]);
    }

    public function handle(): string
    {
        return self::HANDLE;
    }

    protected function defaultSeverity(): Severity
    {
        return Severity::Warning;
    }

    public function check(Document $document): array
    {
        /** @var array<string, array{int, int, int, string, string}> first use of each acronym: block, offset, length, as written, the short form alone */
        $first = [];
        /** @var array<string, array{int, int}> earliest definition of each: block, offset */
        $defined = [];

        foreach ($document->blocks as $index => $block) {
            if ($block->isHeading()) {
                continue;
            }

            preg_match_all(self::CANDIDATE, $block->text, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

            foreach ($matches as $match) {
                [$written, $offset] = $match[0];
                $acronym = self::normalize($match[1][0]);

                if (! self::isAcronym($match[1][0]) || in_array($acronym, $this->ignored, true)) {
                    continue;
                }

                $first[$acronym] ??= [$index, $offset, strlen($written), $written, $match[1][0]];

                if ($this->isDefinedHere($block->text, $offset, strlen($written))) {
                    $defined[$acronym] = self::earliest($defined[$acronym] ?? null, [$index, $offset]);
                }
            }
        }

        // Spelled-out phrases can only be looked for once every acronym is
        // known, and one may come later in the document than the first use.
        foreach ($document->blocks as $index => $block) {
            if (! $block->isHeading()) {
                foreach ($this->expansionsIn($block->text, array_keys($first)) as [$acronym, $offset]) {
                    $defined[$acronym] = self::earliest($defined[$acronym] ?? null, [$index, $offset]);
                }
            }
        }

        $findings = [];

        foreach ($first as $acronym => [$block, $offset, $length, $written, $short]) {
            $where = $defined[$acronym] ?? null;

            if ($where !== null && $where <= [$block, $offset]) {
                continue;
            }

            $findings[] = $this->finding(
                $where === null
                    ? sprintf('"%s" is never spelled out. Write it in full the first time, with the short form after it in brackets, and use the short form from then on.', $short)
                    : sprintf('"%s" is spelled out later than this, its first use. Spell it out here, the first time, and use the short form after that.', $short),
                new Location($block, $offset, $length),
                $written,
            );
        }

        return $findings;
    }

    /**
     * Two capitals at least, and not a Roman numeral. A real numeral, not
     * merely numeral letters: "CDC" is made of them and is not one.
     */
    public static function isAcronym(string $token): bool
    {
        return preg_match_all('/[A-Z]/', $token) >= 2
            && preg_match('/^M{0,4}(?:CM|CD|D?C{0,3})(?:XC|XL|L?X{0,3})(?:IX|IV|V?I{0,3})$/', $token) !== 1;
    }

    /**
     * Whether the acronym at this offset is being defined: in brackets after
     * its expansion, "... Prevention (CDC)", or followed by its expansion
     * in brackets, "CDC (Centers ...)".
     */
    private function isDefinedHere(string $text, int $offset, int $length): bool
    {
        $before = $offset > 0 ? $text[$offset - 1] : '';
        $after = substr($text, $offset + $length);

        return ($before === '(' && str_starts_with($after, ')'))
            || preg_match('/^\s*\(\s*\p{L}/u', $after) === 1;
    }

    /**
     * Phrases in the text whose initials spell one of the acronyms, small
     * words skipped: "World Health Organization" for WHO.
     *
     * @param  list<string>  $acronyms
     * @return iterable<array{string, int}> acronym, offset of the phrase
     */
    private function expansionsIn(string $text, array $acronyms): iterable
    {
        $wanted = [];

        foreach ($acronyms as $acronym) {
            $letters = strtolower((string) preg_replace('/[^A-Za-z]/', '', $acronym));

            if (strlen($letters) >= 2 && strlen($letters) <= 8) {
                $wanted[$letters] = $acronym;
            }
        }

        if ($wanted === []) {
            return;
        }

        preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\'\-]*/u', $text, $tokens, PREG_OFFSET_CAPTURE);
        $tokens = $tokens[0];
        $count = count($tokens);

        for ($start = 0; $start < $count; $start++) {
            $initials = '';

            for ($i = $start, $seen = 0; $i < $count && $seen < 11; $i++, $seen++) {
                $word = strtolower($tokens[$i][0]);

                if (in_array($word, self::SMALL, true)) {
                    if ($i === $start) {
                        break;
                    }

                    continue;
                }

                $initials .= $word[0];

                if (isset($wanted[$initials]) && strtolower($tokens[$start][0]) !== $initials) {
                    yield [$wanted[$initials], $tokens[$start][1]];
                }

                if (strlen($initials) >= 8) {
                    break;
                }
            }
        }
    }

    /**
     * @param  array{int, int}|null  $a
     * @param  array{int, int}  $b
     * @return array{int, int}
     */
    private static function earliest(?array $a, array $b): array
    {
        return $a === null || $b < $a ? $b : $a;
    }

    private static function normalize(string $acronym): string
    {
        return strtoupper(str_replace('.', '', $acronym));
    }
}
