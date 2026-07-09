<?php

declare(strict_types=1);

class PasswordInput extends Input
{
    public function __construct()
    {
        parent::__construct();
        $this -> attributes['type'] = 'password';
    }
}
