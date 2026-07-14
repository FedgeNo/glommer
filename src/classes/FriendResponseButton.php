<?php

declare(strict_types=1);

class FriendResponseButton extends Button
{
    public function __construct(string $action, int $friendship_id)
    {
        parent::__construct();

        $this -> class = 'Btn ' . ($action === 'accept' ? 'AcceptFriendButton' : 'DenyFriendButton');
        $this -> attributes['data-friendship-id'] = (string) $friendship_id;
        $this -> contents[] = $action === 'accept' ? 'Accept' : 'Deny';
    }
}
