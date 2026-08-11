<?php

declare(strict_types=1);

class UserModButton extends ButtonButton
{
    public function __construct(int $user_id, bool $is_mod)
    {
        parent::__construct();

        $this -> attributes['data-user-id'] = (string) $user_id;
        $this -> attributes['data-is-mod'] = $is_mod ? '1' : '0';
        $words = Strings::for(self::class);
        $this -> contents[] = (string) ($words[$is_mod ? 'remove' : 'make'] ?? '');
    }
}
