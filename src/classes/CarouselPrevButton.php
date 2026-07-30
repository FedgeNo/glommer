<?php

declare(strict_types=1);

class CarouselPrevButton extends ButtonButton
{
    public function __construct()
    {
        parent::__construct();

        $this -> attributes['aria-label'] = 'Previous';
        $this -> contents[] = '‹';
    }
}
