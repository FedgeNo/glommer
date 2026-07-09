<?php

declare(strict_types=1);

class TextInput extends Input
{
    public function __construct()
    {
        parent::__construct();
        $this -> attributes['type'] = 'text';
    }
}
