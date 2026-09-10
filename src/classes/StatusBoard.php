<?php

declare(strict_types=1);

/** Live component overview with links to detailed controls. */
class StatusBoard extends Section
{
    public ?string $class = 'StatusBoard';
    public array $tiles = [];

    public function toDOM(): \DOMElement
    {
        $this -> addContent(new Heading2((string) (Strings::for(self::class)['heading'] ?? '')));

        foreach ($this -> tiles as $tile) {
            $this -> addContent(new StatusTile($tile));
        }

        return parent::toDOM();
    }
}
