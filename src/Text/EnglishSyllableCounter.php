<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Text;

/**
 * Syllables by the rules of English spelling: vowel groups, corrected by
 * lists of prefixes, suffixes, sounds that look like two syllables and
 * are one, sounds that look like one and are two, and words that follow
 * no rule at all.
 *
 * Ported from Dave Child's Text-Statistics (BSD-2-Clause; the notice is
 * in THIRD-PARTY-LICENSES.md), which took it from Greg Fast's
 * Lingua::EN::Syllable. Ported rather than depended on: the library's last
 * release was in 2018, it calls a function PHP has deprecated, and these
 * lists are the kind of thing that wants fixing in place when a word
 * comes up wrong. Every departure from the original is marked where it
 * is made, because a change to any rule is a change to every score.
 *
 * Two things happen before the rules, because they know only A to Z:
 *
 * - accents come off, so "café" counts as "cafe" and not as "caf";
 * - an initialism with no vowel in it ("CDC", "HTML") is read letter by
 *   letter, which is how it is said aloud, rather than as one syllable.
 */
final class EnglishSyllableCounter implements SyllableCounter
{
    /**
     * Words the rules get wrong, with the right answer: a sounded trailing
     * "e", mostly, and a few portmanteaus.
     *
     * @var array<string, int>
     */
    private const EXCEPTIONS = [
        'abalone' => 4, 'abare' => 3, 'abed' => 2, 'abruzzese' => 4, 'abbruzzese' => 4, 'aborigine' => 5, 'acreage' => 3,
        'adame' => 3, 'adieu' => 2, 'adobe' => 3, 'anemone' => 4, 'apache' => 3, 'aphrodite' => 4, 'apostrophe' => 4,
        'ariadne' => 4, 'cafe' => 2, 'calliope' => 4, 'catastrophe' => 4, 'chile' => 2, 'chloe' => 2, 'circe' => 2,
        'coyote' => 3, 'epitome' => 4, 'forever' => 3, 'gethsemane' => 4, 'guacamole' => 4, 'hyperbole' => 4, 'jesse' => 2,
        'jukebox' => 2, 'karate' => 3, 'machete' => 3, 'maybe' => 2, 'people' => 2, 'recipe' => 3, 'sesame' => 3,
        'shoreline' => 2, 'simile' => 3, 'syncope' => 3, 'tamale' => 3, 'yosemite' => 4, 'daphne' => 2, 'eurydice' => 4,
        'euterpe' => 3, 'hermione' => 4, 'penelope' => 4, 'persephone' => 4, 'phoebe' => 2, 'zoe' => 2,
        // Added here: the "pro-" prefix rule takes a syllable the rest of
        // the word cannot give back when the rest is a silent "e" or the
        // second half of a diphthong. Found by the reference-text tests.
        'prove' => 1, 'proved' => 1, 'proud' => 1, 'proudly' => 2, 'prouder' => 2, 'proudest' => 2, 'proof' => 1,
        'prose' => 1, 'probe' => 1, 'probed' => 1, 'prone' => 1,
    ];

    /**
     * Sounds the vowel count takes for two syllables that are one.
     *
     * @var list<string>
     */
    private const ONE_NOT_TWO = [
        'cia(l|$)', 'tia', 'cius', 'cious', '[^aeiou]giu', '[aeiouy][^aeiouy]ion', 'iou', 'sia$', 'eous$', '[oa]gue$',
        '.[^aeiuoycgltdb]{2,}ed$', '.ely$', '^jua', 'uai', 'eau',
        // A soft "e" ending. The original's author asks that this not be
        // touched without testing it on: absolve, acquiesce, audience, ache,
        // acquire, brunelle, byrne, canadienne, coughed, curved, champagne,
        // designate, force, lace, late, lathe, make, relayed, scrounge, side,
        // sideline, some, wide, taste.
        '[aeiouy](b|c|ch|d|dg|f|g|gh|gn|k|l|ll|lv|m|mm|n|nc|ng|nn|p|r|rc|rn|rs|rv|s|sc|sk|sl|squ|ss|st|t|th|v|y|z)e$',
        // The same with a "d": crunched, forced, hated, sided, sidelined, unexploded, unexplored, scrounged, squelched.
        '[aeiouy](b|c|ch|dg|f|g|gh|gn|k|l|lch|ll|lv|m|mm|n|nc|ng|nch|nn|p|r|rc|rn|rs|rv|s|sc|sk|sl|squ|ss|th|v|y|z)ed$',
        // And with an "s": absences, accomplices, acknowledges, advantages, byrnes, crunches, forces, scrounges, squelches.
        '[aeiouy](b|ch|d|f|gh|gn|k|l|lch|ll|lv|m|mm|n|nch|nn|p|r|rn|rs|rv|s|sc|sk|sl|squ|ss|st|t|th|v|y)es$',
        '^busi$',
    ];

    /**
     * Sounds the vowel count takes for one syllable that are two.
     *
     * One rule differs from the original: "[aeiouy]sh?e[rsd]$" is now
     * "[aeiouy]sh?e[rs]$". With the "d" it gave every "-sed" and "-shed"
     * past participle a syllable it does not have: "used", "based",
     * "closed", "washed", "finished", "diagnosed", "advised" were all one
     * too many. Found by the reference-text tests; 427 dictionary words
     * changed, each by one, each to the dictionary's answer.
     *
     * @var list<string>
     */
    private const TWO_NOT_ONE = [
        '([^s]|^)ia', 'riet', 'dien', 'iu', 'io', 'eo($|[b-df-hj-np-tv-z])', 'ii', '[ou]a$', '[aeiouym]bl$', '[aeiou]{3}',
        '[aeiou]y[aeiou]', '^mc', 'ism$', 'asm$', 'thm$', '([^aeiouy])\1l$', '[^l]lien', '^coa[dglx].', '[^gq]ua[^auieo]',
        'dnt$', 'uity$', '[^aeiouy]ie(r|st|t)$', 'eings?$', '[aeiouy]sh?e[rs]$', 'iell', 'dea$', 'real', '[^aeiou]y[ae]',
        'gean$', 'uen',
    ];

    /**
     * Prefixes and suffixes of one syllable, taken off and counted before
     * the rest of the word is looked at.
     *
     * @var list<string>
     */
    private const ONE_SYLLABLE_AFFIXES = [
        '/^un/', '/^fore/', '/^ware/', '/^none?/', '/^out/', '/^post/', '/^sub/', '/^pre/', '/^pro/', '/^dis/', '/^side/',
        '/ly$/', '/less$/', '/some$/', '/ful$/', '/ers?$/', '/ness$/', '/cians?$/', '/ments?$/', '/ettes?$/', '/villes?$/',
        '/ships?$/', '/sides?$/', '/ports?$/', '/shires?$/', '/tion(ed)?$/',
    ];

    /** @var list<string> */
    private const TWO_SYLLABLE_AFFIXES = [
        '/^above/', '/^ant[ie]/', '/^counter/', '/^hyper/', '/^afore/', '/^agri/', '/^in[ft]ra/', '/^inter/', '/^over/',
        '/^semi/', '/^ultra/', '/^under/', '/^extra/', '/^dia/', '/^micro/', '/^mega/', '/^kilo/', '/^pico/', '/^nano/',
        '/^macro/', '/berry$/', '/woman$/', '/women$/',
    ];

    /** @var list<string> */
    private const THREE_SYLLABLE_AFFIXES = ['/ology$/', '/ologist$/', '/onomy$/', '/onomist$/'];

    /**
     * How a plural is made singular, so that "recipes" finds "recipe" in
     * the exceptions. Tried in order; the first that matches wins.
     *
     * @var array<string, string>
     */
    private const SINGULAR = [
        '/(quiz)zes$/i' => '$1', '/(matr)ices$/i' => '$1ix', '/(vert|ind)ices$/i' => '$1ex', '/^(ox)en$/i' => '$1',
        '/(alias)es$/i' => '$1', '/(octop|vir)i$/i' => '$1us', '/(cris|ax|test)es$/i' => '$1is', '/(shoe)s$/i' => '$1',
        '/(o)es$/i' => '$1', '/(bus)es$/i' => '$1', '/([m|l])ice$/i' => '$1ouse', '/(x|ch|ss|sh)es$/i' => '$1',
        '/(m)ovies$/i' => '$1ovie', '/(s)eries$/i' => '$1eries', '/([^aeiouy]|qu)ies$/i' => '$1y', '/([lr])ves$/i' => '$1f',
        '/(tive)s$/i' => '$1', '/(hive)s$/i' => '$1', '/(li|wi|kni)ves$/i' => '$1fe', '/(shea|loa|lea|thie)ves$/i' => '$1f',
        '/(^analy)ses$/i' => '$1sis', '/((a)naly|(b)a|(d)iagno|(p)arenthe|(p)rogno|(s)ynop|(t)he)ses$/i' => '$1$2sis',
        '/([ti])a$/i' => '$1um', '/(n)ews$/i' => '$1ews', '/(h|bl)ouses$/i' => '$1ouse', '/(corpse)s$/i' => '$1',
        '/(us)es$/i' => '$1', '/s$/i' => '',
    ];

    /** @var array<string, string> singular => plural */
    private const IRREGULAR = [
        'child' => 'children', 'foot' => 'feet', 'goose' => 'geese', 'man' => 'men', 'mouse' => 'mice', 'move' => 'moves',
        'person' => 'people', 'sex' => 'sexes', 'tooth' => 'teeth',
    ];

    /** @var list<string> */
    private const UNCOUNTABLE = [
        'beef', 'bison', 'buffalo', 'carbon', 'chemistry', 'copper', 'geometry', 'gold', 'cs', 'css', 'deer', 'equipment',
        'fish', 'furniture', 'information', 'mathematics', 'money', 'moose', 'nitrogen', 'oxygen', 'rice', 'series',
        'sheep', 'species', 'surgery', 'traffic', 'water',
    ];

    public function syllables(string $word): int
    {
        $word = Diacritics::strip(trim($word));

        if (preg_match('/[A-Za-z]/', $word) !== 1) {
            return 0;
        }

        if (preg_match('/^[A-Z]{2,}$/', $word) === 1 && preg_match('/[AEIOUY]/', $word) !== 1) {
            return strlen($word);
        }

        $word = strtolower((string) preg_replace('/[^A-Za-z]/', '', $word));

        if (isset(self::EXCEPTIONS[$word])) {
            return self::EXCEPTIONS[$word];
        }

        $singular = $this->singular($word);

        if ($singular !== $word && isset(self::EXCEPTIONS[$singular])) {
            return self::EXCEPTIONS[$singular];
        }

        $word = (string) preg_replace(self::ONE_SYLLABLE_AFFIXES, '', $word, -1, $ones);
        $word = (string) preg_replace(self::TWO_SYLLABLE_AFFIXES, '', $word, -1, $twos);
        $word = (string) preg_replace(self::THREE_SYLLABLE_AFFIXES, '', $word, -1, $threes);

        $groups = count(array_filter(preg_split('/[^aeiouy]+/', $word) ?: [], static fn (string $part): bool => $part !== ''));
        $count = $groups + $ones + 2 * $twos + 3 * $threes;

        foreach (self::ONE_NOT_TWO as $pattern) {
            $count -= preg_match('/'.$pattern.'/', $word);
        }

        foreach (self::TWO_NOT_ONE as $pattern) {
            $count += preg_match('/'.$pattern.'/', $word);
        }

        return max(1, $count);
    }

    private function singular(string $word): string
    {
        if (in_array($word, self::UNCOUNTABLE, true)) {
            return $word;
        }

        foreach (self::IRREGULAR as $singular => $plural) {
            if (preg_match('/'.$singular.'$/i', $word) === 1) {
                return $word;
            }
        }

        foreach (self::IRREGULAR as $singular => $plural) {
            if (preg_match('/'.$plural.'$/i', $word) === 1) {
                return (string) preg_replace('/'.$plural.'$/i', $singular, $word);
            }
        }

        foreach (self::SINGULAR as $pattern => $replacement) {
            if (preg_match($pattern, $word) === 1) {
                return (string) preg_replace($pattern, $replacement, $word);
            }
        }

        return $word;
    }
}
