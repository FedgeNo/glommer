<?php

declare(strict_types=1);

class RelayUnsubscribeButton extends ButtonButton
{
    public function __construct(string $actor_uri)
    {
        parent::__construct();

        $this -> attributes['data-actor-uri'] = $actor_uri;
        $this -> contents[] = (string) (Strings::for(self::class)['name'] ?? '');
    }
}
