<?php

declare(strict_types=1);

class RemoveFilesButton extends Button
{
    public function __construct()
    {
        parent::__construct();

        $this -> class = 'RemoveFilesButton';
        $this -> mixins = ['Button'];
        $this -> attributes['style'] = 'display: none';
        $this -> contents[] = 'Remove Files';
    }
}
