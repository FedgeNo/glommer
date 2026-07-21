<?php

declare(strict_types=1);

class BanButton extends Button
{
    public function __construct(int $user_id, string $label)
    {
        parent::__construct();

        $this -> class = 'Button BanButton';
        $this -> attributes['data-user-id'] = (string) $user_id;
        $this -> contents[] = $label;
    }
}
