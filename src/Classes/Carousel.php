<?php

declare(strict_types=1);

class Carousel extends HTMLObject
{
    // How many items ahead of the current one keep their media loaded - so the
    // viewer always stays this many slides ahead of the loading. The first
    // slide plus this many load up front; the rest defer until the carousel
    // advances toward them (main.js keeps the same buffer filled as it moves),
    // so a large gallery doesn't fetch everything at once. Mirrored client-side
    // in post.js (itemsToCarousel) and main.js's advance buffer, which read it
    // as window.carouselEagerItems (see Page::create).
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
                // Current slide (index 0) plus INITIAL_EAGER_ITEMS ahead load
                // eagerly, hence > rather than >=.
                $item -> deferred = $index > self::INITIAL_EAGER_ITEMS;
            }

            $slide = new Div();
            $slide -> class = 'CarouselSlide' . ($index === 0 ? ' Active' : '');
            $slide -> addContent($item);
            $track -> addContent($slide);
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
