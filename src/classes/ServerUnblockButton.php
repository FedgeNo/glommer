<?php

declare(strict_types=1);

/** Lifts a server block. Carries the domain it acts on. */
class ServerUnblockButton extends ButtonButton
{
    public function __construct(string $domain)
    {
        parent::__construct();

        $this -> type = 'button';
        $this -> attributes['data-domain'] = $domain;
        $this -> contents[] = 'Unblock';
    }
}
