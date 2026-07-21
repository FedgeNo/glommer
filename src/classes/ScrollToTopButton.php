<?php

declare(strict_types=1);

/**
 * Returns a long page to its top. Sits out of the way at the bottom of the
 * viewport, and main.js only reveals it once there's enough scrolled past to
 * be worth the trip back.
 */
class ScrollToTopButton extends Button
{
    public ?string $class = 'ScrollToTopButton';

    public function toDOM(): \DOMElement
    {
        $this -> attributes['aria-label'] = 'Scroll to top';
        $this -> contents[] = 'Scroll to top';

        return parent::toDOM();
    }
}
