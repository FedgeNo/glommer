<?php

declare(strict_types=1);

class FriendSearchBox extends SearchBox
{
    public string $placeholder = 'Search friends...';

    protected function input(): SearchInput
    {
        return new FriendSearchInput(['placeholder' => $this -> placeholder]);
    }
}
