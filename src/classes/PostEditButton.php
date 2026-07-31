<?php

declare(strict_types=1);

/**
 * Opens the inline editor on your own post. Only the author is ever shown one,
 * and api/edit-post.php refuses anyone else regardless.
 */
class PostEditButton extends ButtonButton
{
    public function __construct()
    {
        parent::__construct();

        $this -> contents[] = 'Edit';
    }
}
