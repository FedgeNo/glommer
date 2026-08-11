<?php

declare(strict_types=1);

class BannedUserSection extends UserSection
{
    protected function list(): ItemLoader
    {
        return new BannedUserList(['offset' => $this -> offset]);
    }

    public function toDOM(): \DOMElement
    {
        $this -> heading = (string) (Strings::for(self::class)['heading'] ?? '');

        return parent::toDOM();
    }
}
