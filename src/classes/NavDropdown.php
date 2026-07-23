<?php

declare(strict_types=1);

class NavDropdown extends Div
{
    public ?string $class = 'NavDropdown';

    public HTMLObject|string $trigger;

    /** @var HTMLObject[] menu items - anchors, plus the LogoutForm */
    public array $links;

    public function __construct(HTMLObject|string $trigger, array $links)
    {
        parent::__construct();

        $this -> trigger = $trigger;
        $this -> links = $links;
    }

    public function toDOM(): \DOMElement
    {
        if (is_string($this -> trigger)) {
            $trigger = new Div();
            $trigger -> class = 'NavDropdownTrigger';
            $trigger -> contents[] = $this -> trigger;

            $this -> addContent($trigger);
        } else {
            $this -> trigger -> class = trim(($this -> trigger -> class ?? '') . ' NavDropdownTrigger');

            $this -> addContent($this -> trigger);
        }

        $menu = new Div();
        $menu -> class = 'NavDropdownMenu Card';

        foreach ($this -> links as $link) {
            $menu -> addContent($link);
        }

        $this -> addContent($menu);

        return parent::toDOM();
    }
}
