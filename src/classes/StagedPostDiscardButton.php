<?php

declare(strict_types=1);

/** Deletes a draft or scheduled post without ever publishing it. */
class StagedPostDiscardButton extends ButtonButton
{
    public function __construct()
    {
        parent::__construct();

        $this -> mixins = ['Removing'];
        $this -> contents[] = (string) (Strings::for(self::class)['name'] ?? '');
    }
}
