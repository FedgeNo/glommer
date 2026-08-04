<?php

declare(strict_types=1);

class LinkImageRemoveButton extends ButtonButton
{
    public function __construct()
    {
        parent::__construct();

        $this -> type = 'button';
        $this -> contents[] = 'Remove image';
    }
}
