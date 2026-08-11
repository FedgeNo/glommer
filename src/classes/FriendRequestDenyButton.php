<?php

declare(strict_types=1);

class FriendRequestDenyButton extends ButtonButton
{
    public function __construct(int $friendship_id)
    {
        parent::__construct();

        $this -> attributes['data-friendship-id'] = (string) $friendship_id;
        $this -> contents[] = (string) (Strings::for(self::class)['name'] ?? '');
    }
}
