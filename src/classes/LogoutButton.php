<?php

declare(strict_types=1);

/** The submit inside LogoutForm. */
class LogoutButton extends Button
{
    // Deliberately skips ButtonButton's shared .Button identity: in navigation
    // this is a POST-backed link, not a primary form action.
    public ?string $class = 'LogoutButton';

    public function __construct()
    {
        parent::__construct();

        $this -> type = 'submit';
        $this -> contents[] = (string) (Strings::for(self::class)['name'] ?? '');
    }
}
