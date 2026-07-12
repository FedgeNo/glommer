<?php

declare(strict_types=1);

class Carousel extends HTMLObject
{
    // How many items load their media up front. The rest defer until the
    // carousel advances onto them, so a large gallery doesn't fetch everything
    // at once. Mirrored client-side in post.js (itemsToCarousel).
    public const INITIAL_EAGER_ITEMS = 5;

    public string $tagName = 'div';
    public ?string $class = 'Carousel';

    /** @var HTMLObject[] */
    public array $items = [];

    public function toDOM(): \DOMElement
    {
        $track = new Div();
        $track -> class = 'CarouselTrack';

        foreach ($this -> items as $index => $item) {
            if ($item instanceof FeedItem) {
                $item -> deferred = $index >= self::INITIAL_EAGER_ITEMS;
            }

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

            $this -> contents[] = new CarouselAutoplayButton();
        }

        return parent::toDOM();
    }
}
