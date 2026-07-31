<?php

declare(strict_types=1);

class UserBanButton extends ButtonButton
{
    public function __construct(int $user_id, string $label)
    {
        parent::__construct();

        $this -> attributes['data-user-id'] = (string) $user_id;
        $this -> contents[] = $label;
    }
}
