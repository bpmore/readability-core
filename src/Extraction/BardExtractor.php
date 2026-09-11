<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Extraction;

use Bpmore\ReadabilityCore\Text\Block;
use Bpmore\ReadabilityCore\Text\Document;
use InvalidArgumentException;

/**
 * Blocks from a Bard field's node tree: the ProseMirror JSON Statamic
 * stores, taken as the array it stores or as that array's JSON.
 *
 * The tree is walked rather than rendered because the tree knows things
 * the HTML forgets: which set a paragraph sits in, so a set can be excluded
 * by handle, and the path to every node, so a finding can point back at the
 * exact paragraph in the editor.
 *
 * Sets are containers. A disabled set is skipped whole. An enabled one
 * contributes the Bard fields nested inside it, walked as their own trees.
 * Its plain string fields are left alone: from here there is no telling a
 * caption from a URL, and the host, which has the blueprint, can hand any
 * of them to the plain text extractor itself.
 */
final class BardExtractor implements Extractor
{
    /** Bard node types, as the HTML element each renders to, so the Origin speaks one vocabulary. */
    private const TAGS = [
        'paragraph' => 'p',
        'bulletList' => 'ul',
        'orderedList' => 'ol',
        'listItem' => 'li',
        'blockquote' => 'blockquote',
        'codeBlock' => 'pre',
        'table' => 'table',
        'tableRow' => 'tr',
        'tableCell' => 'td',
        'tableHeader' => 'th',
        'horizontalRule' => 'hr',
        'image' => 'img',
        'hardBreak' => 'br',
    ];

    public function extract(string|array $source): Document
    {
        if (is_string($source)) {
            if (trim($source) === '') {
                return Document::empty();
            }

            $decoded = json_decode($source, true);

            if (! is_array($decoded)) {
                throw new InvalidArgumentException('BardExtractor reads a Bard node tree: the array Statamic stores, or its JSON.');
            }

            $source = $decoded;
        }

        $blocks = [];
        $root = Frame::root();
        $this->walkAll($this->nodes($source), $root, '', $blocks);
        // A text node at the top level is not a shape Bard saves, but the
        // words in one are still words.
        $root->flush($blocks);

        return new Document($blocks);
    }

    /**
     * The nodes of a Bard value, whichever way it was wrapped: the bare list
     * Statamic saves, a ProseMirror `doc`, or a single node.
     *
     * @param  array<mixed>  $value
     * @return list<mixed>
     */
    private function nodes(array $value): array
    {
        if (isset($value['type'])) {
            return $value['type'] === 'doc' ? array_values((array) ($value['content'] ?? [])) : [$value];
        }

        return array_values($value);
    }

    /**
     * @param  list<mixed>  $nodes
     * @param  list<Block>  $blocks
     */
    private function walkAll(array $nodes, Frame $frame, string $path, array &$blocks): void
    {
        foreach ($nodes as $index => $node) {
            if (! is_array($node) || ! is_string($node['type'] ?? null)) {
                continue;
            }

            $this->walk($node, $frame, $path === '' ? (string) $index : $path.'.'.$index, $blocks);
        }
    }

    /**
     * @param  array<mixed>  $node
     * @param  list<Block>  $blocks
     */
    private function walk(array $node, Frame $frame, string $path, array &$blocks): void
    {
        $type = $node['type'];

        if ($type === 'text') {
            // The editor shows a newline inside a text node as a space; a
            // break the author made is a hardBreak node, handled below.
            $frame->text .= is_string($node['text'] ?? null) ? (string) preg_replace('/[\t\n\v\f\r ]+/', ' ', $node['text']) : '';

            return;
        }

        if ($type === 'hardBreak') {
            $frame->text .= "\n";

            return;
        }

        if ($type === 'set') {
            $this->walkSet($node, $frame, $path, $blocks);

            return;
        }

        // A leaf with nothing to read: an image, a rule, a mention, some
        // extension's own node. Nothing to do.
        if (! is_array($node['content'] ?? null)) {
            return;
        }

        $level = $type === 'heading' ? max(1, min(6, (int) ($node['attrs']['level'] ?? 1))) : 0;
        $tag = $type === 'heading' ? 'h'.$level : (self::TAGS[$type] ?? $type);

        $frame->flush($blocks);
        $inner = $frame->child($tag, level: $level, path: $path);
        $this->walkAll(array_values($node['content']), $inner, $path, $blocks);
        $inner->flush($blocks);
    }

    /**
     * @param  array<mixed>  $node
     * @param  list<Block>  $blocks
     */
    private function walkSet(array $node, Frame $frame, string $path, array &$blocks): void
    {
        $attrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];

        if (($attrs['enabled'] ?? true) === false) {
            return;
        }

        $values = is_array($attrs['values'] ?? null) ? $attrs['values'] : [];
        $handle = is_string($values['type'] ?? null) ? $values['type'] : null;

        $frame->flush($blocks);
        $inner = $frame->child('set', sets: $handle === null ? [] : [$handle], path: $path);

        foreach ($values as $field => $value) {
            if ($field === 'type' || ! $this->looksLikeBard($value)) {
                continue;
            }

            $this->walkAll($this->nodes($value), $inner, $path.'.'.$field, $blocks);
        }
    }

    /**
     * Whether a set's field value is itself a Bard tree: a node, or a list
     * that starts with one. A Replicator's rows also have a `type`, and are
     * walked and found to hold nothing readable, which is the right answer.
     */
    private function looksLikeBard(mixed $value): bool
    {
        if (! is_array($value) || $value === []) {
            return false;
        }

        if (is_string($value['type'] ?? null)) {
            return true;
        }

        $first = reset($value);

        return is_array($first) && is_string($first['type'] ?? null);
    }
}
