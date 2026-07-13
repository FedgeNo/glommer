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
        } else {
            $trigger = $this -> trigger;
            $trigger -> class = trim(($trigger -> class ?? '') . ' NavDropdownTrigger');
        }

        $this -> addContents($trigger);

        $menu = new Div();
        $menu -> class = 'NavDropdownMenu Card';

        foreach ($this -> links as $link) {
            $menu -> addContents($link);
        }

        $this -> addContents($menu);

        return parent::toDOM();
    }
}
