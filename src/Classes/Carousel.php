<?php

declare(strict_types=1);

class Carousel extends HTMLObject
{
    public string $tagName = 'div';
    public ?string $class = 'Carousel';

    /** @var HTMLObject[] */
    public array $items = [];

    public function toDOM(): \DOMElement
    {
        $track = new Div();
        $track -> class = 'CarouselTrack';

        foreach ($this -> items as $index => $item) {
            $slide = new Div();
            $slide -> class = 'CarouselSlide' . ($index === 0 ? ' Active' : '');
            $slide -> addContents($item);
            $track -> addContents($slide);
        }

        $this -> contents[] = $track;

        if (count($this -> items) > 1) {
            $this -> contents[] = new CarouselNavButton('prev');
            $this -> contents[] = new CarouselNavButton('next');

            $counter = new Div();
            $counter -> class = 'CarouselCounter';
            $counter -> contents[] = '1 / ' . count($this -> items);
            $this -> contents[] = $counter;
        }

        return parent::toDOM();
    }
}
