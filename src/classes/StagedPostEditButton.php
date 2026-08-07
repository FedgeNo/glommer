<?php

declare(strict_types=1);

/** Opens a draft or scheduled post for editing, in place of its card. */
class StagedPostEditButton extends ButtonButton
{
    public function __construct()
    {
        parent::__construct();

        $this -> contents[] = 'Edit';
    }
}
