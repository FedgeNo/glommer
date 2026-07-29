<?php

declare(strict_types=1);

class DenyFriendButton extends ButtonButton
{
    public function __construct(int $friendship_id)
    {
        parent::__construct();

        $this -> attributes['data-friendship-id'] = (string) $friendship_id;
        $this -> contents[] = 'Deny';
    }
}
