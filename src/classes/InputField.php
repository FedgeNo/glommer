<?php

declare(strict_types=1);

class InputField extends Div
{
    public ?string $class = 'InputField';

    public string $name;
    public string $label;
    public string $type = 'text';
    public ?string $placeholder = null;
    public string $value = '';
    public ?int $maxLength = null;
    public ?string $autocomplete = null;
    public bool $labelVisible = true;

    /**
     * Why this input was refused, shown under it.
     *
     * Set where the server already knows before the page is drawn. The client
     * puts the same element in the same place when an endpoint answers with a
     * field it would not accept - see Runtime.js's FormErrors, which has to agree with
     * what is built here.
     */
    public ?string $error = null;

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
        $this -> contents[] = new FieldLabel($this);

        $input = self::inputForType($this -> type, $this);
        $input -> id = $this -> name;

        $this -> contents[] = $input;

        if ($this -> error !== null) {
            $this -> contents[] = self::errorElement($this -> name, $this -> error);
        }

        return parent::toDOM();
    }

    /**
     * The reason under a refused input, built the one way so the server and
     * the client cannot drift into showing it differently.
     */
    public static function errorElement(string $name, string $reason): Paragraph
    {
        $error = new Paragraph();
        $error -> class = 'FieldError';
        $error -> attributes['id'] = $name . 'Error';
        $error -> contents[] = $reason;

        return $error;
    }

    protected static function inputForType(string $type, array|object|null $properties = null): Input
    {
        return match ($type) {
            'email' => new EmailInput($properties),
            'password' => new PasswordInput($properties),
            default => new TextInput($properties),
        };
    }
}
