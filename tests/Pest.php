<?php

declare(strict_types=1);

// Nothing is booted here on purpose. The engine takes text and returns
// findings; a suite that boots no framework for it cannot grow a dependency
// on something booted, which is the boundary the arch test below enforces.
