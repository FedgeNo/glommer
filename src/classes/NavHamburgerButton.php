<?php

declare(strict_types=1);

/**
 * The mobile-only trigger for MobileNavMenu - three bars built from plain
 * divs (styled in CSS), toggling the panel via a delegated click handler in
 * main.js. Hidden entirely above the mobile nav breakpoint.
 */
class NavHamburgerButton extends Button
{
    public ?string $class = 'NavHamburgerButton';

    public function toDOM(): \DOMElement
    {
        $this -> attributes['aria-label'] = 'Menu';
        $this -> attributes['aria-expanded'] = 'false';

        for ($i = 0; $i < 3; $i++) {
            $bar = new Div();
            $bar -> class = 'NavHamburgerBar';
            $this -> addContent($bar);
        }

        return parent::toDOM();
    }
}
