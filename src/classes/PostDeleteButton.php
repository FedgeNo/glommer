<?php

declare(strict_types=1);

/**
 * Deletes your own post. On a permalink page there is no card left to remove
 * once it is gone, so data-standalone tells Post.js to send the reader home
 * instead of animating away the page they are on.
 */
class PostDeleteButton extends ButtonButton
{
    public function __construct(bool $standalone = false)
    {
        parent::__construct();

        if ($standalone) {
            $this -> attributes['data-standalone'] = '1';
        }

        $this -> contents[] = (string) (Strings::for(self::class)['name'] ?? '');
    }
}
