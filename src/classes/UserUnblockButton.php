<?php

declare(strict_types=1);

class UserUnblockButton extends ButtonButton
{
    public function __construct(int $user_id)
    {
        parent::__construct();

        $this -> type = 'button';
        $this -> mixins = ['ms-auto'];
        $this -> attributes['data-user-id'] = (string) $user_id;
        $this -> contents[] = (string) (Strings::for(self::class)['name'] ?? '');
    }
}
