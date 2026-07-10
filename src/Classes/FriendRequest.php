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

        $element = parent::toDOM();

        $element -> appendChild((new FriendResponseButton('accept', (int) $this -> friendshipId)) -> toDOM());
        $element -> appendChild((new FriendResponseButton('deny', (int) $this -> friendshipId)) -> toDOM());

        return $element;
    }
}
