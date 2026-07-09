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

        $accept_button = new Button();
        $accept_button -> type = 'button';
        $accept_button -> class = 'Btn AcceptFriendButton';
        $accept_button -> attributes['data-friendship-id'] = (string) $this -> friendshipId;
        $accept_button -> contents[] = 'Accept';
        $element -> appendChild($accept_button -> toDOM());

        $deny_button = new Button();
        $deny_button -> type = 'button';
        $deny_button -> class = 'Btn DenyFriendButton';
        $deny_button -> attributes['data-friendship-id'] = (string) $this -> friendshipId;
        $deny_button -> contents[] = 'Deny';
        $element -> appendChild($deny_button -> toDOM());

        return $element;
    }
}
