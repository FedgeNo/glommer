<?php

declare(strict_types=1);

class EmailInput extends Input
{
    public function __construct()
    {
        parent::__construct();
        $this -> attributes['type'] = 'email';
    }
}
