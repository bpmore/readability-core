<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Text;

/**
 * What a block of text is, coarsely. The tag it came from is on the
 * Origin; this is the handful of distinctions the rules and the exclusions
 * care about, so nothing downstream has to know that a Bard `codeBlock` and
 * an HTML `<pre>` are the same thing.
 */
enum BlockKind: string
{
    case Heading = 'heading';
    case Paragraph = 'paragraph';
    case ListItem = 'list_item';
    case Quote = 'quote';
    case Code = 'code';
    case Cell = 'cell';
    case Caption = 'caption';
    case Other = 'other';

    /**
     * The kind for an element, by its HTML tag name. Anything block-level
     * that is none of the named things (a `div`, a `section`, an `address`)
     * is Other, and keeps its tag on the Origin for anyone who needs it.
     */
    public static function forTag(string $tag): self
    {
        return match ($tag) {
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6' => self::Heading,
            'p' => self::Paragraph,
            'li', 'dt' => self::ListItem,
            'blockquote' => self::Quote,
            'pre' => self::Code,
            'td', 'th' => self::Cell,
            'figcaption', 'caption' => self::Caption,
            default => self::Other,
        };
    }
}
