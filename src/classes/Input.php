<?php

declare(strict_types=1);

/**
 * The base <input>: everything an input has regardless of type (a name). It
 * deliberately has no value - a file input can't carry one, so value lives on
 * ValueInput, which every value-bearing input type descends from instead.
 */
class Input extends HTMLVoidElement
{
    protected const INPUT_TYPE = null;

    public string $tagName = 'input';
    public ?string $name = null;
    public ?string $placeholder = null;
    public ?int $maxLength = null;
    public ?string $autocomplete = null;
    public ?string $error = null;

    public function toDOM(): \DOMElement
    {
        if (static::INPUT_TYPE !== null) {
            $this -> attributes['type'] ??= static::INPUT_TYPE;
        }

        if ($this -> placeholder !== null) {
            $this -> attributes['placeholder'] = $this -> placeholder;
        }

        if ($this -> maxLength !== null) {
            $this -> attributes['maxlength'] = (string) $this -> maxLength;
        }

        if ($this -> autocomplete !== null) {
            $this -> attributes['autocomplete'] = $this -> autocomplete;
        }

        if ($this -> error !== null) {
            $this -> attributes['aria-invalid'] = 'true';
            $this -> attributes['aria-describedby'] = $this -> name . 'Error';
        }

        if ($this -> name === '') {
            throw new Exception('Input name must not be an empty string');
        }

        if ($this -> name !== null) {
            $this -> attributes['name'] = $this -> name;
        }

        return parent::toDOM();
    }
}
