<?php

declare(strict_types=1);

class TextareaField extends HTMLObject
{
    public string $tagName = 'div';
    public ?string $class = 'TextareaField';

    public string $name;
    public string $label;
    public ?string $placeholder = null;
    public ?int $maxLength = null;

    public function __construct(string $name, string $label, ?string $placeholder = null, ?int $max_length = null)
    {
        parent::__construct();

        $this -> name = $name;
        $this -> label = $label;
        $this -> placeholder = $placeholder ?? $label;
        $this -> maxLength = $max_length;
    }

    public function toDOM(): \DOMElement
    {
        $label = new Label();
        $label -> for = $this -> name;
        $label -> class = 'visually-hidden';
        $label -> contents[] = $this -> label;
        $this -> contents[] = $label;

        $textarea = new Textarea();
        $textarea -> name = $this -> name;
        $textarea -> id = $this -> name;
        $textarea -> attributes['placeholder'] = $this -> placeholder;

        if ($this -> maxLength !== null) {
            $textarea -> attributes['maxlength'] = (string) $this -> maxLength;
        }

        $this -> contents[] = $textarea;

        return parent::toDOM();
    }
}
