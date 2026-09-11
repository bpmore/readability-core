<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Text;

/**
 * Where a block came from: enough to exclude it by what encloses it, and to
 * point a finding back at it.
 *
 * Both extractors speak HTML tag names here, so a rule can ask "is this
 * inside a blockquote" once, whether the source was markup or a Bard node
 * tree. Bard node types are mapped to the tag they render as (`listItem` is
 * `li`, `codeBlock` is `pre`); a set is `set`, which has no HTML equivalent.
 */
final class Origin
{
    /**
     * @param  string  $tag  the element the text sits in
     * @param  list<string>  $ancestors  the elements enclosing it, outermost first; `html` and `body` are not recorded
     * @param  list<string>  $classes  HTML classes on the element and its ancestors, nearest last, without duplicates
     * @param  list<string>  $sets  Bard set handles the block sits inside, outermost first
     * @param  ?string  $path  the Bard node path, as child indices joined with dots (`3.0`, or `2.body.1` through a set's field); null for anything but Bard
     */
    public function __construct(
        public readonly string $tag,
        public readonly array $ancestors = [],
        public readonly array $classes = [],
        public readonly array $sets = [],
        public readonly ?string $path = null,
    ) {}

    /** The element directly enclosing this one, if any. */
    public function parent(): ?string
    {
        return $this->ancestors === [] ? null : $this->ancestors[count($this->ancestors) - 1];
    }

    /** Whether any of the given elements encloses this block, or is it. */
    public function within(string ...$tags): bool
    {
        return array_intersect($tags, [...$this->ancestors, $this->tag]) !== [];
    }
}
