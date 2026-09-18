<?php

declare(strict_types=1);

class CheckboxInput extends ValueInput
{
    protected const INPUT_TYPE = 'checkbox';

    public bool $checked = false;

    public function toDOM(): \DOMElement
    {
        if ($this -> checked) {
            $this -> attributes['checked'] = 'checked';
        }

        return parent::toDOM();
    }
}
