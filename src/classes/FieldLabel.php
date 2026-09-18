<?php

declare(strict_types=1);

/** A field's label owns its target, text, and visibility. */
class FieldLabel extends Label
{
    protected const HYDRATION_EXCLUSIONS = ['id'];

    public string $name = '';
    public string $label = '';
    public bool $labelVisible = true;

    public function toDOM(): \DOMElement
    {
        $this -> for = $this -> name;
        $this -> class = $this -> labelVisible ? null : 'visually-hidden';
        $this -> contents[] = $this -> label;

        return parent::toDOM();
    }
}
