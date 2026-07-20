<?php

declare(strict_types=1);

class UserSearchBox extends SearchBox
{
    public string $placeholder = 'Search for a user...';

    protected function input(): SearchInput
    {
        return new UserSearchInput(['placeholder' => $this -> placeholder]);
    }
}
