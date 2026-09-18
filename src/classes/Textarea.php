<?php

declare(strict_types=1);

class Textarea extends HTMLObject
{
    public string $tagName = 'textarea';
    public ?string $name = null;
    public string $value = '';
    public ?string $placeholder = null;
    public ?int $maxLength = null;
    public ?string $error = null;

    public function toDOM(): \DOMElement
    {
        $this -> attributes['dir'] = 'auto';

        if ($this -> placeholder !== null) {
            $this -> attributes['placeholder'] = $this -> placeholder;
        }

        if ($this -> maxLength !== null) {
            $this -> attributes['maxlength'] = (string) $this -> maxLength;
        }

        if ($this -> value !== '') {
            $this -> contents[] = $this -> value;
        }

        if ($this -> error !== null) {
            $this -> attributes['aria-invalid'] = 'true';
            $this -> attributes['aria-describedby'] = $this -> name . 'Error';
        }

        if ($this -> name !== null) {
            $this -> attributes['name'] = $this -> name;
        }

        return parent::toDOM();
    }
}
