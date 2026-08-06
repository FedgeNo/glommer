<?php

declare(strict_types=1);

/**
 * Defederating a server, and the list of the ones already shut out. Short
 * enough to read in one go, which is why it lives on the Mod Settings page
 * rather than on a page of its own.
 */
class BlockedServersSetting extends Div
{
    public ?string $class = 'BlockedServersSetting';

    public function toDOM(): \DOMElement
    {
        $this -> addContent(new ServerBlockForm());
        $this -> addContent(new BlockedServerList());

        return parent::toDOM();
    }
}
