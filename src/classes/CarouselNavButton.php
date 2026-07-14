<?php

declare(strict_types=1);

class CarouselNavButton extends Button
{
    public function __construct(string $direction)
    {
        parent::__construct();

        $this -> class = $direction === 'prev' ? 'CarouselPrev' : 'CarouselNext';
        $this -> attributes['aria-label'] = $direction === 'prev' ? 'Previous' : 'Next';
        $this -> contents[] = $direction === 'prev' ? '‹' : '›';
    }
}
