<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Text;

/**
 * Text that remembers where every byte of it came from.
 *
 * The engine cleans text before it counts or checks anything: it folds
 * whitespace, straightens quotes, drops invisible characters, and cuts
 * addresses and citations out of sentences. A finding's position is in that
 * cleaned text. An editor needs it in the text the author wrote. This keeps,
 * for every byte of the cleaned text, the stretch of the written text it
 * came from, so a position can be carried back.
 *
 * Each operation mirrors the PCRE call it replaces and returns new text
 * with the map carried through: a byte that is copied keeps its origin; a
 * byte a replacement wrote takes the whole stretch the match covered. So a
 * straightened quote maps to the curly one, a folded run of spaces to the
 * whole run, and text after a cut to where it really stands.
 *
 * Offsets are bytes, like everything else in the engine.
 */
final class Tracked
{
    /**
     * @param  list<int>  $starts  for each byte of $text, where its stretch of the source starts
     * @param  list<int>  $ends  and where it ends
     * @param  int  $sourceLength  the length of the source, for a position at the very end
     */
    private function __construct(
        public readonly string $text,
        private readonly array $starts,
        private readonly array $ends,
        private readonly int $sourceLength,
    ) {}

    /**
     * Text that is its own source, or a piece of a longer source starting at $at.
     */
    public static function of(string $text, int $at = 0, ?int $sourceLength = null): self
    {
        $length = strlen($text);
        $starts = $length === 0 ? [] : range($at, $at + $length - 1);
        $ends = $length === 0 ? [] : range($at + 1, $at + $length);

        return new self($text, $starts, $ends, $sourceLength ?? $at + $length);
    }

    /**
     * Pieces of one source, one after another.
     */
    public static function concat(int $sourceLength, self ...$parts): self
    {
        $text = '';
        $starts = [];
        $ends = [];

        foreach ($parts as $part) {
            $text .= $part->text;
            array_push($starts, ...$part->starts);
            array_push($ends, ...$part->ends);
        }

        return new self($text, $starts, $ends, $sourceLength);
    }

    /**
     * Where a stretch of this text came from in the source: start and end,
     * in bytes. A stretch of nothing is a point.
     *
     * @return array{int, int}
     */
    public function span(int $offset, int $length): array
    {
        $start = $this->starts[$offset] ?? $this->sourceLength;

        if ($length <= 0) {
            return [$start, $start];
        }

        $end = $this->ends[min($offset + $length, strlen($this->text)) - 1] ?? $start;

        return [$start, max($start, $end)];
    }

    /**
     * As preg_replace with one pattern, the map carried through. The
     * replacement may use `$1` to `$99` for a captured group, which keeps
     * the group's own origins.
     */
    public function replace(string $regex, string $replacement): self
    {
        if (preg_match($regex, $this->text) !== 1) {
            return $this;
        }

        $tokens = self::tokens($replacement);

        return $this->rebuild($regex, function (array $match) use ($tokens): array {
            $pieces = [];

            foreach ($tokens as $token) {
                $pieces[] = is_int($token) ? $match[$token] ?? ['', -1] : $token;
            }

            return $pieces;
        });
    }

    /**
     * As preg_replace_callback: the callback is given the matched text and
     * returns what to write in its place.
     *
     * @param  callable(string): string  $replacement
     */
    public function replaceWith(string $regex, callable $replacement): self
    {
        if (preg_match($regex, $this->text) !== 1) {
            return $this;
        }

        return $this->rebuild($regex, static fn (array $match): array => [$replacement($match[0][0])]);
    }

    /**
     * As trim() with its default characters.
     */
    public function trim(): self
    {
        $trimmed = trim($this->text);

        if ($trimmed === $this->text) {
            return $this;
        }

        return $this->slice(strlen($this->text) - strlen(ltrim($this->text)), strlen($trimmed));
    }

    public function slice(int $offset, ?int $length = null): self
    {
        $length ??= strlen($this->text) - $offset;

        return new self(
            substr($this->text, $offset, $length),
            array_slice($this->starts, $offset, $length),
            array_slice($this->ends, $offset, $length),
            $this->sourceLength,
        );
    }

    /**
     * As explode(): the pieces between each separator, each with the
     * separator that followed it, so a join can put back the real one.
     *
     * @return list<array{self, ?self}>
     */
    public function split(string $separator): array
    {
        $parts = [];
        $at = 0;
        $length = strlen($separator);

        while (($next = strpos($this->text, $separator, $at)) !== false) {
            $parts[] = [$this->slice($at, $next - $at), $this->slice($next, $length)];
            $at = $next + $length;
        }

        $parts[] = [$this->slice($at), null];

        return $parts;
    }

    /**
     * As implode(), each piece followed by its own separator except the last.
     *
     * @param  list<array{self, ?self}>  $parts
     */
    public function join(array $parts): self
    {
        $pieces = [];
        $last = count($parts) - 1;

        foreach ($parts as $i => [$piece, $separator]) {
            $pieces[] = $piece;

            if ($i < $last && $separator !== null) {
                $pieces[] = $separator;
            }
        }

        return self::concat($this->sourceLength, ...$pieces);
    }

    /**
     * Rebuild the text with every match replaced by the pieces $replace
     * gives: a string, written in place of the whole match, or a captured
     * group as [text, offset], copied with its origins.
     *
     * @param  callable(list<array{string, int}>): list<string|array{string, int}>  $replace
     */
    private function rebuild(string $regex, callable $replace): self
    {
        preg_match_all($regex, $this->text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        $text = '';
        $starts = [];
        $ends = [];
        $at = 0;

        foreach ($matches as $match) {
            [$whole, $offset] = $match[0];

            $this->copy($at, $offset - $at, $text, $starts, $ends);

            [$from, $to] = $this->span($offset, strlen($whole));

            foreach ($replace($match) as $piece) {
                if (is_array($piece)) {
                    if ($piece[1] >= 0) {
                        $this->copy($piece[1], strlen($piece[0]), $text, $starts, $ends);
                    }

                    continue;
                }

                $text .= $piece;

                for ($i = 0, $n = strlen($piece); $i < $n; $i++) {
                    $starts[] = $from;
                    $ends[] = $to;
                }
            }

            $at = $offset + strlen($whole);
        }

        $this->copy($at, strlen($this->text) - $at, $text, $starts, $ends);

        return new self($text, $starts, $ends, $this->sourceLength);
    }

    /**
     * @param  list<int>  $starts
     * @param  list<int>  $ends
     */
    private function copy(int $offset, int $length, string &$text, array &$starts, array &$ends): void
    {
        if ($length <= 0) {
            return;
        }

        $text .= substr($this->text, $offset, $length);
        array_push($starts, ...array_slice($this->starts, $offset, $length));
        array_push($ends, ...array_slice($this->ends, $offset, $length));
    }

    /**
     * A replacement string as literal pieces and group numbers.
     *
     * @return list<string|int>
     */
    private static function tokens(string $replacement): array
    {
        $parts = preg_split('/(\$\d{1,2}|\\\\\d{1,2})/', $replacement, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];

        return array_map(static fn (string $part): string|int => preg_match('/^(?:\$|\\\\)(\d{1,2})$/', $part, $m) === 1 ? (int) $m[1] : $part, $parts);
    }
}
