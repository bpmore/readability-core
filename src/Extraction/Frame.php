<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Extraction;

use Bpmore\ReadabilityCore\Text\Block;
use Bpmore\ReadabilityCore\Text\BlockKind;
use Bpmore\ReadabilityCore\Text\Normalizer;
use Bpmore\ReadabilityCore\Text\Origin;
use Bpmore\ReadabilityCore\Text\Tracked;

/**
 * The block currently being collected while an extractor walks a tree:
 * where it is, and the text gathered so far. Inline content appends to the
 * open frame; a block-level child closes it and opens its own.
 *
 * @internal shared by the extractors; not part of the package's surface
 */
final class Frame
{
    /** What the author wrote, as it arrives: every piece, ignored or not. */
    private string $written = '';

    /** @var list<Tracked> the pieces the engine reads, each placed in */
    private array $pieces = [];

    /**
     * @param  list<string>  $ancestors
     * @param  list<string>  $classes
     * @param  list<string>  $sets
     * @param  list<string>  $ids
     * @param  list<string>  $roles
     */
    private function __construct(
        public readonly string $tag,
        public readonly array $ancestors,
        public readonly array $classes,
        public readonly array $sets,
        public readonly ?string $path,
        public readonly int $level,
        public readonly array $ids,
        public readonly array $roles,
        private readonly bool $isRoot,
    ) {}

    /**
     * The document itself, before any element has opened. Text that sits
     * here belongs to no block, and gets `body` as its tag; the root is never
     * recorded as an ancestor of anything.
     */
    public static function root(): self
    {
        return new self('body', [], [], [], null, 0, [], [], isRoot: true);
    }

    /**
     * A frame for a block-level element opening inside this one.
     *
     * @param  list<string>  $classes
     * @param  list<string>  $sets
     * @param  list<string>  $ids
     * @param  list<string>  $roles
     */
    public function child(string $tag, array $classes = [], int $level = 0, array $sets = [], ?string $path = null, array $ids = [], array $roles = []): self
    {
        return new self(
            $tag,
            $this->isRoot ? [] : [...$this->ancestors, $this->tag],
            array_values(array_unique([...$this->classes, ...$classes])),
            [...$this->sets, ...$sets],
            $path,
            $level,
            [...$this->ids, ...$ids],
            array_values(array_unique([...$this->roles, ...$roles])),
            isRoot: false,
        );
    }

    /**
     * Text the reader reads. Folded unless it is preformatted: a newline in
     * the source is how an editor wrapped a line, not a break, so it becomes
     * a space here, and a newline that reaches the normaliser is always a
     * break somebody put there.
     */
    public function addText(string $text, bool $fold = true): void
    {
        $piece = Tracked::of($text, strlen($this->written));

        $this->pieces[] = $fold ? $piece->replace('/[\t\n\v\f\r ]+/', ' ') : $piece;
        $this->written .= $text;
    }

    /**
     * Text that is in the block as written but not read: inline code, an
     * ignored mark. Kept in `written` so positions match the editor's text.
     */
    public function addIgnored(string $text): void
    {
        $this->written .= $text;
    }

    /** A break the author made: `<br>`, a Bard hardBreak. */
    public function addBreak(): void
    {
        $this->pieces[] = Tracked::of("\n", strlen($this->written));
        $this->written .= "\n";
    }

    /**
     * Emit what has been collected as a block, if it says anything, and
     * start collecting again. Called when a block-level child opens, so the
     * text before it stands on its own, and when the element closes.
     *
     * @param  list<Block>  $blocks
     */
    public function flush(array &$blocks): void
    {
        $written = $this->written;
        $tracked = Tracked::concat(strlen($written), ...$this->pieces);

        $this->written = '';
        $this->pieces = [];

        // Not UTF-8: cleaned the way normalize() does, and without positions,
        // because scrubbing changes byte lengths the pieces were placed by.
        if (! mb_check_encoding($tracked->text, 'UTF-8')) {
            $text = Normalizer::normalize($tracked->text);
            $tracked = null;
        } else {
            $tracked = Normalizer::track($tracked);
            $text = $tracked->text;
        }

        if ($text === '') {
            return;
        }

        $blocks[] = new Block(
            BlockKind::forTag($this->tag),
            $text,
            new Origin($this->tag, $this->ancestors, $this->classes, $this->sets, $this->path, $this->ids, $this->roles),
            $this->level,
            mb_check_encoding($written, 'UTF-8') ? $written : null,
            $tracked,
        );
    }
}
