<?php

declare(strict_types=1);

class ReceivedFriendRequestSection extends UserSection
{
    protected function list(): ItemLoader
    {
        return new ReceivedFriendRequestList(['user' => $this -> user, 'offset' => $this -> offset]);
    }

    public function toDOM(): \DOMElement
    {
        $this -> heading = (string) (Strings::for(self::class)['heading'] ?? '');

        return parent::toDOM();
    }
}
