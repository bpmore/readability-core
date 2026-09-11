<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Text;

/**
 * Latin letters with their marks taken off: "café" as "cafe", "naïve" as
 * "naive". A syllable counter built on the English alphabet drops any
 * letter it does not know, and with it the vowel that made the syllable.
 *
 * A table rather than intl's decomposition, so the answer is the same on
 * every host. The letters are the ones English text borrows.
 */
final class Diacritics
{
    private const TABLE = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'ā' => 'a', 'ă' => 'a', 'ą' => 'a', 'æ' => 'ae',
        'ç' => 'c', 'ć' => 'c', 'č' => 'c', 'ď' => 'd', 'đ' => 'd',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ē' => 'e', 'ė' => 'e', 'ę' => 'e', 'ě' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ī' => 'i', 'į' => 'i', 'ı' => 'i',
        'ł' => 'l', 'ñ' => 'n', 'ń' => 'n', 'ň' => 'n',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o', 'ō' => 'o', 'ő' => 'o', 'œ' => 'oe',
        'ř' => 'r', 'ś' => 's', 'š' => 's', 'ş' => 's', 'ß' => 'ss', 'ť' => 't', 'ţ' => 't',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ū' => 'u', 'ů' => 'u', 'ű' => 'u', 'ų' => 'u',
        'ý' => 'y', 'ÿ' => 'y', 'ź' => 'z', 'ż' => 'z', 'ž' => 'z',
        'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Ā' => 'A', 'Ă' => 'A', 'Ą' => 'A', 'Æ' => 'AE',
        'Ç' => 'C', 'Ć' => 'C', 'Č' => 'C', 'Ď' => 'D', 'Đ' => 'D',
        'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ē' => 'E', 'Ė' => 'E', 'Ę' => 'E', 'Ě' => 'E',
        'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ī' => 'I', 'Į' => 'I', 'İ' => 'I',
        'Ł' => 'L', 'Ñ' => 'N', 'Ń' => 'N', 'Ň' => 'N',
        'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O', 'Ō' => 'O', 'Ő' => 'O', 'Œ' => 'OE',
        'Ř' => 'R', 'Ś' => 'S', 'Š' => 'S', 'Ş' => 'S', 'Ť' => 'T', 'Ţ' => 'T',
        'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ū' => 'U', 'Ů' => 'U', 'Ű' => 'U', 'Ų' => 'U',
        'Ý' => 'Y', 'Ÿ' => 'Y', 'Ź' => 'Z', 'Ż' => 'Z', 'Ž' => 'Z',
    ];

    public static function strip(string $text): string
    {
        return strtr($text, self::TABLE);
    }
}
