<?php

declare(strict_types=1);

/**
 * A labeled checkbox form field - the checkbox followed by its visible label
 * (unlike InputField, whose label is visually hidden behind the placeholder,
 * a checkbox has no placeholder so the label carries the meaning).
 */
class CheckboxField extends Div
{
    public ?string $class = 'CheckboxField';

    public string $name;
    public string $label;
    public bool $checked = false;
    public string $value = '1';

    public function __construct(string $name, string $label)
    {
        parent::__construct();

        $this -> name = $name;
        $this -> label = $label;
    }

    public function toDOM(): \DOMElement
    {
        $checkbox = new CheckboxInput($this);
        $checkbox -> id = $this -> name;

        $this -> contents[] = $checkbox;

        $this -> contents[] = new FieldLabel($this);

        return parent::toDOM();
    }
}
