<?php

declare(strict_types=1);

class UnbanButton extends Button
{
    public function __construct(int $user_id)
    {
        parent::__construct();

        $this -> class = 'Btn UnbanButton';
        $this -> attributes['data-user-id'] = (string) $user_id;
        $this -> contents[] = 'Unban';
    }
}
