<?php

declare(strict_types=1);

class InputField extends HTMLObject
{
    public string $tagName = 'div';
    public ?string $class = 'InputField';

    public string $name;
    public string $label;
    public string $type = 'text';
    public ?string $placeholder = null;
    public string $value = '';
    public ?int $maxLength = null;
    public ?string $autocomplete = null;

    public function __construct(string $name, string $label, string $type = 'text', ?string $placeholder = null, ?int $max_length = null)
    {
        parent::__construct();

        $this -> name = $name;
        $this -> label = $label;
        $this -> type = $type;
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

        $input = self::inputForType($this -> type);
        $input -> name = $this -> name;
        $input -> id = $this -> name;
        $input -> value = $this -> value;
        $input -> attributes['placeholder'] = $this -> placeholder;

        if ($this -> maxLength !== null) {
            $input -> attributes['maxlength'] = (string) $this -> maxLength;
        }

        if ($this -> autocomplete !== null) {
            $input -> attributes['autocomplete'] = $this -> autocomplete;
        }

        $this -> contents[] = $input;

        return parent::toDOM();
    }

    protected static function inputForType(string $type): Input
    {
        return match ($type) {
            'email' => new EmailInput(),
            'password' => new PasswordInput(),
            default => new TextInput(),
        };
    }
}
