<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Rule;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * What every rule found, in reading order: by block, then by position in
 * it; findings about the whole document come last.
 *
 * @implements IteratorAggregate<int, Finding>
 */
final class Findings implements Countable, IteratorAggregate
{
    /** @var list<Finding> */
    private readonly array $findings;

    /**
     * @param  list<Finding>  $findings
     */
    public function __construct(array $findings)
    {
        usort($findings, static function (Finding $a, Finding $b): int {
            if ($a->location === null || $b->location === null) {
                return ($a->location === null) <=> ($b->location === null);
            }

            return [$a->location->block, $a->location->offset, $a->severity->rank()]
                <=> [$b->location->block, $b->location->offset, $b->severity->rank()];
        });

        $this->findings = array_values($findings);
    }

    public static function none(): self
    {
        return new self([]);
    }

    /**
     * @return list<Finding>
     */
    public function all(): array
    {
        return $this->findings;
    }

    public function ofRule(string ...$handles): self
    {
        return $this->filter(static fn (Finding $f): bool => in_array($f->rule, $handles, true));
    }

    public function ofSeverity(Severity ...$severities): self
    {
        return $this->filter(static fn (Finding $f): bool => in_array($f->severity, $severities, true));
    }

    public function inBlock(int $index): self
    {
        return $this->filter(static fn (Finding $f): bool => $f->location?->block === $index);
    }

    public function aboutWholeDocument(): self
    {
        return $this->filter(static fn (Finding $f): bool => $f->location === null);
    }

    public function merge(self $other): self
    {
        return new self([...$this->findings, ...$other->findings]);
    }

    public function isEmpty(): bool
    {
        return $this->findings === [];
    }

    public function count(): int
    {
        return count($this->findings);
    }

    /**
     * @return Traversable<int, Finding>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->findings);
    }

    /**
     * @param  callable(Finding): bool  $keep
     */
    private function filter(callable $keep): self
    {
        return new self(array_values(array_filter($this->findings, $keep)));
    }
}
