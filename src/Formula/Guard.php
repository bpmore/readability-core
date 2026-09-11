<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Formula;

use InvalidArgumentException;

/**
 * @internal
 */
final class Guard
{
    public static function gradable(Counts $counts): void
    {
        if ($counts->isEmpty()) {
            throw new InvalidArgumentException('There is nothing to grade: no words, or no sentences. Check Counts::isEmpty() first.');
        }
    }
}
