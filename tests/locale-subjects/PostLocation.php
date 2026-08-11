<?php

declare(strict_types=1);

/** @return array<string, callable(): HTMLObject> */

return [
    // Coordinates rather than a place name, so the pair and its separator are
    // what gets rendered - the branch that has words of its own to leak.
    PostLocationLink::class => static fn (): HTMLObject => new PostLocationLink(45.4215, -75.6972),
];
