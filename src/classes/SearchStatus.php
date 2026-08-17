<?php

declare(strict_types=1);

/**
 * The live region a SearchBox announces its results through. Visually hidden,
 * spoken: results swapping in under the box are silent to a screen reader,
 * so Search.js writes "3 results" here when they land and the reader says it.
 */
class SearchStatus extends Div
{
    public ?string $class = 'SearchStatus';

    public array $mixins = ['visually-hidden'];

    public function toDOM(): \DOMElement
    {
        $this -> attributes['role'] = 'status';
        $this -> attributes['aria-live'] = 'polite';

        return parent::toDOM();
    }
}
