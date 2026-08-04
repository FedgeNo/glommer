<?php

declare(strict_types=1);

class RememberedDeviceRevokeButton extends ButtonButton
{
    public function __construct(int $token_id)
    {
        parent::__construct();

        $this -> type = 'button';
        $this -> mixins = ['ms-auto'];
        $this -> attributes['data-token-id'] = (string) $token_id;
        $this -> contents[] = 'Revoke';
    }
}
