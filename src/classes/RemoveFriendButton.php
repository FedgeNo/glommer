<?php

declare(strict_types=1);

class RemoveFriendButton extends Button
{
    public function __construct(int $user_id)
    {
        parent::__construct();

        $this -> type = 'button';
        $this -> class = 'Btn RemoveFriendButton';
        $this -> attributes['data-user-id'] = (string) $user_id;
        $this -> contents[] = 'Remove Friend';
    }
}
