<?php

declare(strict_types=1);

class CarouselNextButton extends ButtonButton
{
    public function __construct()
    {
        parent::__construct();

        $this -> attributes['aria-label'] = 'Next';
        $this -> contents[] = '›';
    }
}
