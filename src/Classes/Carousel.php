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
            $prev_button = new Button();
            $prev_button -> type = 'button';
            $prev_button -> class = 'CarouselPrev';
            $prev_button -> attributes['aria-label'] = 'Previous';
            $prev_button -> contents[] = '‹';
            $this -> contents[] = $prev_button;

            $next_button = new Button();
            $next_button -> type = 'button';
            $next_button -> class = 'CarouselNext';
            $next_button -> attributes['aria-label'] = 'Next';
            $next_button -> contents[] = '›';
            $this -> contents[] = $next_button;

            $counter = new Div();
            $counter -> class = 'CarouselCounter';
            $counter -> contents[] = '1 / ' . count($this -> items);
            $this -> contents[] = $counter;
        }

        return parent::toDOM();
    }
}
