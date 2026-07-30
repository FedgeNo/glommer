<?php

declare(strict_types=1);

// One <outline> node in an OPML document. OPML allows any outline to nest
// further outlines regardless of its own type, so a user's outline holds one
// child outline per post the same way any other DOMObject holds children.
class OPMLOutline extends XMLObject
{
    public string $tagName = 'outline';
}
