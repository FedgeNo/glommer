<?php

declare(strict_types=1);

// The XML layer over DOMObject. DOMObject already renders a <tagName> with
// attributes and string/child content, which is all XML needs - no CSS class,
// no void-element or self-closing fixups, since valid XML self-closes an empty
// element - so this adds only the content-appending helper its builders use.
// RSSItem and a feed's leaf elements are XMLObjects.
class XMLObject extends DOMObject
{
    public function addContent(XMLObject|string $item): void
    {
        $this -> contents[] = $item;
    }
}
