<?php

declare(strict_types=1);

class Input extends HTMLVoidElement
{
    public string $tagName = 'input';
    public ?string $name = null;
    public string $value = '';

    public function toDOM(): \DOMElement
    {
        if ($this -> name === '') {
            throw new Exception('Input name must not be an empty string');
        }

        if ($this -> name !== null) {
            $this -> attributes['name'] = $this -> name;
        }

        $this -> attributes['value'] = $this -> value;

        return parent::toDOM();
    }
}
