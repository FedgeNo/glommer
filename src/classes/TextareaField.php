<?php

declare(strict_types=1);

class TextareaField extends Div
{
    public ?string $class = 'TextareaField';
    public string $name;
    public string $label;
    public ?string $placeholder = null;
    public ?int $maxLength = null;
    public string $value = '';
    public bool $labelVisible = true;

    /** Why this box was refused, shown under it - see InputField::$error. */
    public ?string $error = null;

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
        $this -> contents[] = new FieldLabel($this);

        $textarea = new Textarea($this);
        $textarea -> id = $this -> name;

        $this -> contents[] = $textarea;

        if ($this -> error !== null) {
            $this -> contents[] = InputField::errorElement($this -> name, $this -> error);
        }

        return parent::toDOM();
    }
}
