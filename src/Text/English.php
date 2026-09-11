<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Text;

/**
 * The word lists the English rules share: what a period does not end,
 * what a sentence ordinarily starts with, what sits inside a name. Kept
 * in one place so the segmenter and the proper-name exclusion cannot
 * drift apart on whether "Inc." ends a sentence.
 *
 * Everything in this package is calibrated on English and says so; these
 * lists are where that shows.
 */
final class English
{
    /**
     * Honorifics and prefixes that lead a name. Their period never ends a
     * sentence, because a name follows: "Dr. Smith", "St. Louis".
     *
     * @var list<string>
     */
    public const TITLES = [
        'dr', 'mr', 'mrs', 'ms', 'mx', 'prof', 'sen', 'rep', 'gov', 'rev', 'hon', 'sr', 'jr', 'st', 'mt', 'ft',
        'gen', 'col', 'capt', 'lt', 'sgt', 'maj', 'cpl', 'pvt', 'cmdr', 'adm', 'pres', 'fr', 'br', 'msgr', 'supt',
        'insp', 'det', 'atty', 'messrs',
    ];

    /**
     * Abbreviations whose period is their own. One may still end a
     * sentence ("at 3 p.m. Please be on time"), so a period after one is a
     * boundary only when what follows reads like a sentence's start.
     *
     * @var list<string>
     */
    public const ABBREVIATIONS = [
        'e.g', 'i.e', 'etc', 'vs', 'cf', 'approx', 'no', 'nos', 'inc', 'ltd', 'co', 'corp', 'dept', 'est', 'fig',
        'vol', 'pp', 'p', 'ed', 'eds', 'ext', 'tel', 'misc', 'univ', 'assn', 'bros',
        'jan', 'feb', 'mar', 'apr', 'jun', 'jul', 'aug', 'sep', 'sept', 'oct', 'nov', 'dec',
        'mon', 'tue', 'tues', 'wed', 'thu', 'thur', 'thurs', 'fri', 'sat', 'sun',
        'a.m', 'p.m', 'ph.d', 'm.d', 'b.a', 'm.a', 'b.s', 'm.s', 'r.n',
    ];

    /**
     * Words a sentence ordinarily starts with. After an abbreviation, one
     * of these with a capital means a new sentence; at the front of one,
     * a capital on one of these is just how the sentence begins.
     *
     * @var list<string>
     */
    public const OPENERS = [
        'a', 'an', 'the', 'and', 'but', 'or', 'nor', 'so', 'yet', 'for', 'of', 'in', 'on', 'at', 'to', 'by', 'with', 'from',
        'into', 'onto', 'over', 'under', 'about', 'after', 'before', 'during', 'until', 'while', 'when', 'whenever',
        'where', 'wherever', 'why', 'how', 'what', 'whatever', 'which', 'who', 'whom', 'whose', 'this', 'that', 'these',
        'those', 'it', 'its', 'he', 'she', 'they', 'them', 'his', 'her', 'hers', 'their', 'theirs', 'we', 'us', 'our', 'ours',
        'you', 'your', 'yours', 'my', 'me', 'mine', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'am', 'do', 'does',
        'did', 'have', 'has', 'had', 'will', 'would', 'can', 'could', 'should', 'may', 'might', 'must', 'shall', 'not',
        'no', 'yes', 'if', 'then', 'than', 'as', 'because', 'since', 'although', 'though', 'unless', 'whether', 'also',
        'too', 'very', 'just', 'only', 'even', 'still', 'here', 'there', 'now', 'today', 'tomorrow', 'yesterday',
        'tonight', 'first', 'second', 'third', 'next', 'last', 'finally', 'however', 'meanwhile', 'therefore',
        'otherwise', 'instead', 'again', 'often', 'sometimes', 'usually', 'always', 'never', 'once', 'soon', 'later',
        'please', 'call', 'ask', 'visit', 'bring', 'take', 'get', 'make', 'use', 'see', 'read', 'learn', 'find', 'check',
        'contact', 'schedule', 'book', 'talk', 'tell', 'let', 'keep', 'try', 'go', 'come', 'stay', 'wait', 'remember',
        'note', 'thank', 'thanks', 'welcome', 'meet', 'join', 'help', 'give', 'send', 'show', 'watch', 'avoid', 'stop',
        'start', 'follow', 'choose', 'consider', 'expect', 'know', 'think', 'feel', 'want', 'need', 'like', 'each', 'every',
        'all', 'some', 'many', 'most', 'any', 'both', 'few', 'several', 'other', 'another', 'such', 'one', 'two', 'three',
        'new', 'more', 'less', 'good', 'great', 'best', 'well', 'sorry', 'hello', 'hi', 'dear', 'according', 'unlike',
        'without', 'within', 'across', 'along', 'among', 'around', 'behind', 'beyond', 'despite', 'except', 'inside',
        'outside', 'through', 'throughout', 'toward', 'towards', 'upon', 'via', 'i\'m', 'i\'ve', 'i\'ll', 'i\'d',
    ];

    /**
     * Lowercase words that sit inside a name without being capitalised.
     *
     * @var list<string>
     */
    public const CONNECTORS = ['of', 'for', 'and', 'the', 'de', 'da', 'di', 'du', 'del', 'della', 'van', 'von', 'der', 'den', 'la', 'le', 'les', 'y', 'e', '&', 'at', 'on', 'in'];

    /**
     * The words a title leaves in lower case.
     *
     * @var list<string>
     */
    public const SMALL_IN_TITLES = [
        'a', 'an', 'the', 'and', 'but', 'or', 'nor', 'for', 'of', 'to', 'in', 'on', 'at', 'by', 'with', 'as', 'vs', 'via',
        'from', 'into', 'onto', 'over', 'per', 'up', 'de', 'da', 'di', 'du', 'del', 'van', 'von', 'der', 'la', 'le', 'y', '&',
    ];

    /** Whether a word is one of the dotted abbreviations: "U.S", "a.m", "Ph.D". Case does not matter. */
    public static function isDotted(string $word): bool
    {
        return preg_match('/^(?:[A-Za-z]{1,2}\.)+[A-Za-z]{1,2}$/', $word) === 1;
    }
}
