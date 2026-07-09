<?php

declare(strict_types=1);

class Meta extends HTMLVoidElement
{
    public string $tagName = 'meta';
    public ?string $charset = null;
    public ?string $name = null;
    public ?string $property = null;
    public ?string $content = null;
    public ?string $httpEquiv = null;

    public function toDOM(): \DOMElement
    {
        if ($this -> charset !== null) {
            $this -> attributes['charset'] = $this -> charset;
        }

        if ($this -> name !== null) {
            $this -> attributes['name'] = $this -> name;
        }

        if ($this -> property !== null) {
            $this -> attributes['property'] = $this -> property;
        }

        if ($this -> content !== null) {
            $this -> attributes['content'] = $this -> content;
        }

        if ($this -> httpEquiv !== null) {
            $this -> attributes['http-equiv'] = $this -> httpEquiv;
        }

        return parent::toDOM();
    }
}
