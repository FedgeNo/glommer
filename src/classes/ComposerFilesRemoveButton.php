<?php

declare(strict_types=1);

class ComposerFilesRemoveButton extends ButtonButton
{
    public function __construct()
    {
        parent::__construct();

        $this -> attributes['style'] = 'display: none';
        $this -> contents[] = 'Remove Files';
    }
}
