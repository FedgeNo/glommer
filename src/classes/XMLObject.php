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

    /**
     * XML 1.0 cannot represent most C0 control characters at all - not even
     * escaped - so a single one anywhere in a post's text would make the whole
     * feed unparseable and every reader would drop it, not just that item.
     * Dropped at the point of serialization rather than at write time: the post
     * still says what its author typed everywhere else, and this covers text
     * that arrived before any write-time rule existed. Tab, newline and
     * carriage return are the three XML does allow.
     */
    protected function contentToNode($item): ?\DOMNode
    {
        if (is_string($item)) {
            $item = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $item);
        }

        return parent::contentToNode($item);
    }
}
