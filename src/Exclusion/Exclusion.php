<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Exclusion;

use Bpmore\ReadabilityCore\Text\Document;

/**
 * Text that should not count. Applied before anything is scored, because
 * a score of the wrong text is not an estimate, it is a different number.
 *
 * An exclusion drops whole blocks (a code block, a quotation), or takes
 * spans out of a block's text (a phone number, a name), or both. It gets
 * the whole document because some of them need the neighbours: a reference
 * list is the blocks after a heading that says "References".
 */
interface Exclusion
{
    public function apply(Document $document): Document;
}
