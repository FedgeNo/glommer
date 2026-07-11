<?php

declare(strict_types=1);

/**
 * A CDATA section - text that rides through literally, unescaped and
 * unparsed as markup, rather than being HTML-entity-escaped like a plain
 * string content item would be. Drop it into any HTMLObject's contents[]
 * array (handled by contentToNode()) - e.g. for a future user-supplied
 * inline <script>, where the content shouldn't be entity-escaped.
 */
class CData
{
    public function __construct(public readonly string $text)
    {
    }

    public function toNode(): \DOMCdataSection
    {
        return HTMLObject::currentDocument() -> createCDATASection($this -> text);
    }
}
