<?php

declare(strict_types=1);

class CarouselNextButton extends Button
{
    public function __construct()
    {
        parent::__construct();

        $this -> class = 'CarouselNextButton';
        $this -> attributes['aria-label'] = 'Next';
        $this -> contents[] = '›';
    }
}
