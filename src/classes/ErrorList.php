<?php

declare(strict_types=1);

class ErrorList extends UnorderedList
{
    public ?string $class = 'ErrorList d-flex flex-column gap-1';

    /** @var string[] */
    public array $errors;

    public function __construct(array $errors)
    {
        parent::__construct();

        $this -> errors = $errors;
    }

    public function toDOM(): \DOMElement
    {
        foreach ($this -> errors as $error) {
            $item = new ListItem();
            $item -> class = 'Error';
            $item -> contents[] = $error;
            $this -> contents[] = $item;
        }

        return parent::toDOM();
    }
}
