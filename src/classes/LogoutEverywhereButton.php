<?php

declare(strict_types=1);

class LogoutEverywhereButton extends ButtonButton
{
    public function __construct()
    {
        parent::__construct();

        $this -> contents[] = 'Log out everywhere';
    }
}
