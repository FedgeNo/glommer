<?php

declare(strict_types=1);

class BannedUserSearchBox extends SearchBox
{
    public string $placeholder = 'Search banned users...';

    protected function input(): SearchInput
    {
        return new BannedUserSearchInput(['placeholder' => $this -> placeholder]);
    }
}
