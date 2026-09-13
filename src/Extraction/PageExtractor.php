<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Extraction;

use Bpmore\ReadabilityCore\Text\Document;
use DOMDocument;
use DOMElement;
use DOMXPath;
use InvalidArgumentException;

/**
 * Blocks from a whole page as a site served it: the part of the page that
 * is the page.
 *
 * A rendered page carries its own furniture, and a reader does not read
 * it as prose: the navigation, the banner, the footer's small print, the
 * sidebar. Graded with the content, a two-paragraph notice on a site with
 * a forty-link footer scores as the footer. So a page's `<main>` (or the
 * element with `role="main"`, for a theme that predates the tag) is what
 * is read; where there is none, the body is, with the landmarks a browser
 * would announce as navigation, banner, contentinfo and complementary left
 * out.
 *
 * The first `<main>` wins where there are several, because a page has one
 * main content by the HTML specification and a page that breaks that rule
 * still has a first one. A `<header>` or `<footer>` is only the site's
 * when it is not inside an article, section, aside, nav or main, which is
 * the rule a browser applies before it announces one as a landmark: inside
 * an article they are the article's own, and they stay.
 */
final class PageExtractor implements Extractor
{
    /** Elements that are furniture wherever they are. */
    private const LANDMARK_TAGS = ['nav', 'aside'];

    /** Elements that are furniture only outside sectioning content. */
    private const SCOPED_LANDMARK_TAGS = ['header', 'footer'];

    private const SECTIONING = ['article', 'aside', 'main', 'nav', 'section'];

    /** The same landmarks, as a theme spells them on a `<div>`. */
    private const LANDMARK_ROLES = ['navigation', 'banner', 'contentinfo', 'complementary'];

    private readonly HtmlExtractor $html;

    /**
     * @param  list<string>  $ignoredTags  handed on to the HTML extractor: elements to leave out with everything inside them
     * @param  list<string>  $ignoredClasses  handed on: classes that mark an element to leave out
     */
    public function __construct(array $ignoredTags = [], array $ignoredClasses = [])
    {
        $this->html = new HtmlExtractor($ignoredTags, $ignoredClasses);
    }

    public function extract(string|array $source): Document
    {
        if (! is_string($source)) {
            throw new InvalidArgumentException('PageExtractor reads a page as a string of HTML.');
        }

        if (trim($source) === '') {
            return Document::empty();
        }

        $dom = $this->load($source);
        $xpath = new DOMXPath($dom);

        $main = $xpath->query('//main | //*[@role="main"]')?->item(0);

        if ($main instanceof DOMElement) {
            return $this->html->extract((string) $dom->saveHTML($main));
        }

        // No main content named: everything but the furniture. Removed from
        // the tree rather than skipped by tag, so a `<div role="navigation">`
        // goes the same way a `<nav>` does.
        $furniture = [];

        foreach (self::LANDMARK_TAGS as $tag) {
            foreach ($xpath->query("//{$tag}") ?: [] as $element) {
                $furniture[] = $element;
            }
        }

        $outside = implode(' or ', array_map(static fn (string $tag): string => "ancestor::{$tag}", self::SECTIONING));

        foreach (self::SCOPED_LANDMARK_TAGS as $tag) {
            foreach ($xpath->query("//{$tag}[not({$outside})]") ?: [] as $element) {
                $furniture[] = $element;
            }
        }

        foreach (self::LANDMARK_ROLES as $role) {
            foreach ($xpath->query("//*[@role='{$role}']") ?: [] as $element) {
                $furniture[] = $element;
            }
        }

        foreach ($furniture as $element) {
            $element->parentNode?->removeChild($element);
        }

        $body = $xpath->query('//body')?->item(0);

        return $this->html->extract((string) $dom->saveHTML($body instanceof DOMElement ? $body : $dom));
    }

    private function load(string $html): DOMDocument
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        // The same prolog the HTML extractor uses, for the same reason: libxml
        // reads undeclared markup as Latin-1, and the page comes back through
        // `saveHTML` to be read again, so it has to survive the round trip.
        $dom->loadHTML('<?xml encoding="utf-8"?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $dom;
    }
}
