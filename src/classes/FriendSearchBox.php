<?php

declare(strict_types=1);

class FriendSearchBox extends SearchBox
{
    public string $placeholder = '';

    public function __construct(array|object|null $properties = null)
    {
        $this -> placeholder = (string) (Strings::for(self::class)['placeholder'] ?? '');
        parent::__construct($properties);
    }

    protected function input(): SearchInput
    {
        return new FriendSearchInput(['placeholder' => $this -> placeholder]);
    }
}
