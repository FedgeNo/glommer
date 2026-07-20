<?php

declare(strict_types=1);

/**
 * The text field of a SearchBox. Autocomplete is off because the browser's own
 * history dropdown covers the live results as they arrive.
 */
class SearchInput extends TextInput
{
    public ?string $class = 'SearchInput';

    public string $placeholder = '';

    public function toDOM(): \DOMElement
    {
        $this -> name = 'q';
        $this -> attributes['placeholder'] = $this -> placeholder;
        $this -> attributes['autocomplete'] = 'off';

        return parent::toDOM();
    }
}
