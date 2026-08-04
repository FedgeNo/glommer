<?php

declare(strict_types=1);

/**
 * The submit inside LogoutForm. Deliberately not a ButtonButton: in the nav
 * it renders as a menu entry, and the shared button face would fight the
 * menu styling.
 */
class LogoutButton extends Button
{
    public ?string $class = 'LogoutButton';

    public function __construct()
    {
        parent::__construct();

        $this -> type = 'submit';
        $this -> contents[] = 'Log out';
    }
}
