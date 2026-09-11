<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Extraction;

use Bpmore\ReadabilityCore\Text\Block;
use Bpmore\ReadabilityCore\Text\Document;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use InvalidArgumentException;

/**
 * Blocks from HTML: a rendered field, a rendered markdown field, or a whole
 * page. The markup is walked as a browser would lay it out. Block-level
 * elements start blocks, inline elements do not, and a `<br>` is a hard
 * break inside the block it sits in.
 *
 * Bad markup is expected. Unclosed tags, HTML5 elements libxml has never
 * heard of, a fragment with no `<body>`: all of it parses, because a
 * readability check that refuses a page is a check nobody runs.
 */
final class HtmlExtractor implements Extractor
{
    /**
     * Elements a reader never reads as prose. Dropped with everything inside
     * them: code that runs, styles, media and its fallbacks, form controls.
     * An image's alt text is left out on purpose; it is written for a
     * different reader and scored, if at all, by a different tool.
     */
    private const DROPPED = [
        'head', 'title', 'script', 'style', 'noscript', 'template',
        'svg', 'math', 'canvas', 'iframe', 'object', 'embed', 'video', 'audio', 'map', 'img', 'picture',
        'select', 'option', 'datalist', 'textarea', 'input', 'button', 'progress', 'meter',
    ];

    /**
     * Elements that start a new block. Anything not here and not dropped is
     * inline, which is also what a browser does with an element it does not
     * know, so a custom element's text lands in the block around it.
     */
    private const BLOCK = [
        'html', 'body',
        'address', 'article', 'aside', 'blockquote', 'caption', 'center', 'dd', 'details', 'dialog', 'div', 'dl', 'dt',
        'fieldset', 'figcaption', 'figure', 'footer', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hgroup',
        'hr', 'legend', 'li', 'main', 'menu', 'nav', 'ol', 'p', 'pre', 'search', 'section', 'summary',
        'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'ul',
    ];

    /** The document's own wrappers. They open no block of their own and are not recorded as ancestors. */
    private const WRAPPERS = ['html', 'body'];

    /**
     * @param  list<string>  $ignoredTags  elements to leave out with everything inside them, inline or block: `code` for inline code, say
     * @param  list<string>  $ignoredClasses  classes that mark an element to leave out the same way
     *
     * Both exist because an inline element's text is part of its block by
     * the time an exclusion sees a Block. A site's "ignore this" class on a
     * `<span>` can only be honoured here.
     */
    public function __construct(
        private readonly array $ignoredTags = [],
        private readonly array $ignoredClasses = [],
    ) {}

    public function extract(string|array $source): Document
    {
        if (! is_string($source)) {
            throw new InvalidArgumentException('HtmlExtractor reads HTML as a string; for a Bard node tree use BardExtractor.');
        }

        if (trim($source) === '') {
            return Document::empty();
        }

        $blocks = [];
        $root = Frame::root();
        $this->walk($this->load($source), $root, $blocks);
        // Text that belongs to no element, and had no block after it to
        // close it: a fragment that is nothing but words.
        $root->flush($blocks);

        return new Document($blocks);
    }

    /**
     * @param  list<Block>  $blocks
     * @param  bool  $preformatted  inside a `<pre>`, where a newline in the source is a line break the author meant
     */
    private function walk(DOMNode $node, Frame $frame, array &$blocks, bool $preformatted = false): void
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                // A newline in the source is how the editor wrapped the line,
                // not a break, everywhere but in preformatted text. Folded to
                // a space here so that a newline reaching the normaliser is
                // always a break somebody put there.
                $frame->text .= $preformatted ? $child->data : (string) preg_replace('/[\t\n\v\f\r ]+/', ' ', $child->data);

                continue;
            }

            // Comments, doctypes, the encoding prolog: nothing a reader sees.
            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->nodeName);
            $classes = $this->classes($child);

            if (in_array($tag, self::DROPPED, true)
                || in_array($tag, $this->ignoredTags, true)
                || array_intersect($this->ignoredClasses, $classes) !== []
                || $this->isHidden($child)) {
                continue;
            }

            if ($tag === 'br') {
                $frame->text .= "\n";

                continue;
            }

            if (! in_array($tag, self::BLOCK, true)) {
                $this->walk($child, $frame, $blocks, $preformatted);

                continue;
            }

            // A block-level child: whatever the open block has gathered so
            // far stands on its own, before this one.
            $frame->flush($blocks);

            if (in_array($tag, self::WRAPPERS, true)) {
                $this->walk($child, $frame, $blocks, $preformatted);

                continue;
            }

            $inner = $frame->child(
                $tag,
                $classes,
                $this->level($tag),
                ids: $this->tokens($child, 'id'),
                roles: $this->tokens($child, 'role'),
            );
            $this->walk($child, $inner, $blocks, $preformatted || $tag === 'pre');
            $inner->flush($blocks);
        }
    }

    private function load(string $html): DOMDocument
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        // libxml assumes Latin-1 unless the markup declares a charset; the XML
        // prolog forces UTF-8 so accents and curly quotes arrive as themselves
        // rather than as bytes the normaliser cannot recognise.
        $dom->loadHTML('<?xml encoding="utf-8"?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $dom;
    }

    /** Content the page itself hides is not on the page for a reader. */
    private function isHidden(DOMElement $element): bool
    {
        return $element->hasAttribute('hidden')
            || preg_match('/display\s*:\s*none/i', $element->getAttribute('style')) === 1;
    }

    /**
     * @return list<string>
     */
    private function classes(DOMElement $element): array
    {
        return $this->tokens($element, 'class');
    }

    /**
     * A space-separated attribute's values. `role` takes a list too: a
     * fallback role after the one the author means.
     *
     * @return list<string>
     */
    private function tokens(DOMElement $element, string $attribute): array
    {
        return array_values(array_filter(preg_split('/\s+/', $element->getAttribute($attribute)) ?: []));
    }

    private function level(string $tag): int
    {
        return preg_match('/^h([1-6])$/', $tag, $m) === 1 ? (int) $m[1] : 0;
    }
}
