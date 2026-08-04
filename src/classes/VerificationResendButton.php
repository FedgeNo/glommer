<?php

declare(strict_types=1);

class VerificationResendButton extends ButtonButton
{
    public function __construct()
    {
        parent::__construct();

        $this -> type = 'button';
        $this -> contents[] = 'Resend verification email';
    }
}
