<?php

declare(strict_types=1);

class TextInput extends ValueInput
{
    protected const INPUT_TYPE = 'text';

    public function toDOM(): \DOMElement
    {
        $this -> attributes['dir'] = 'auto';

        return parent::toDOM();
    }
}
