<?php

declare(strict_types=1);

class GoogleAccountDeleteButton extends ButtonButton
{
    public function __construct()
    {
        parent::__construct();

        $this -> type = 'button';
        $this -> contents[] = 'Verify with Google to delete';
    }
}
