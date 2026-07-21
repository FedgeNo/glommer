<?php

declare(strict_types=1);

class ModButton extends Button
{
    public function __construct(int $user_id, bool $is_mod)
    {
        parent::__construct();

        $this -> class = 'Button ModButton';
        $this -> attributes['data-user-id'] = (string) $user_id;
        $this -> attributes['data-is-mod'] = $is_mod ? '1' : '0';
        $this -> contents[] = $is_mod ? 'Remove Mod' : 'Make Mod';
    }
}
