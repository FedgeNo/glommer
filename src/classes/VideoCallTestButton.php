<?php

declare(strict_types=1);

class VideoCallTestButton extends ButtonButton
{
    public function __construct()
    {
        parent::__construct();

        $this -> contents[] = 'Run the check';
    }
}
