<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Formula;

/**
 * Where a band sits against the target. Above is the one that matters:
 * the text asks more of a reader than the site meant it to. Below is
 * not a problem, and nothing should treat it as one.
 */
enum Comparison: string
{
    case OnTarget = 'on_target';
    case Above = 'above';
    case Below = 'below';
}
