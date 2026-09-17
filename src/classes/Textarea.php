<?php

declare(strict_types=1);

class Textarea extends HTMLObject
{
    public string $tagName = 'textarea';
    public ?string $name = null;

    public function toDOM(): \DOMElement
    {
        $this -> attributes['dir'] = 'auto';

        if ($this -> name !== null) {
            $this -> attributes['name'] = $this -> name;
        }

        return parent::toDOM();
    }
}
