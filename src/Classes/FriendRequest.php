<?php

declare(strict_types=1);

class FriendRequest extends OtherUser
{
    public ?int $friendshipId = null;

    public function toDOM(): \DOMElement
    {
        if ($this -> friendshipId !== null) {
            $this -> attributes['data-friendship-id'] = (string) $this -> friendshipId;
        }

        return parent::toDOM();
    }

    protected function beforeActions(): array
    {
        return [
            new FriendResponseButton('accept', (int) $this -> friendshipId),
            new FriendResponseButton('deny', (int) $this -> friendshipId),
        ];
    }
}
