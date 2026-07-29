<?php

declare(strict_types=1);

class CarouselPrevButton extends Button
{
    public function __construct()
    {
        parent::__construct();

        $this -> class = 'CarouselPrevButton';
        $this -> attributes['aria-label'] = 'Previous';
        $this -> contents[] = '‹';
    }
}
