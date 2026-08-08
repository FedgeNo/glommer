<?php

declare(strict_types=1);

/**
 * Closes the welcome. Whether it stays closed is the checkbox beside it -
 * this only ever means "not now".
 */
class WelcomeBannerDismissButton extends ButtonButton
{
    public ?string $class = 'WelcomeBannerDismissButton';

    public function __construct()
    {
        parent::__construct();

        $this -> contents[] = 'Got It';
    }
}
