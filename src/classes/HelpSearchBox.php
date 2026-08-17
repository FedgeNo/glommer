<?php

declare(strict_types=1);

class HelpSearchBox extends SearchBox
{
    public function __construct(array|object|null $properties = null)
    {
        $this -> placeholder = (string) (Strings::for(HelpSearch::class)['placeholder'] ?? '');

        parent::__construct($properties);
    }

    protected function input(): SearchInput
    {
        return new HelpSearchInput(['placeholder' => $this -> placeholder]);
    }
}
