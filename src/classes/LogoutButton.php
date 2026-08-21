<?php

declare(strict_types=1);

/** The submit inside LogoutForm. */
class LogoutButton extends ButtonButton
{
    public function __construct()
    {
        parent::__construct();

        $this -> type = 'submit';
        $this -> contents[] = (string) (Strings::for(self::class)['name'] ?? '');
    }
}
