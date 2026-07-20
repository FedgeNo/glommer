<?php

declare(strict_types=1);

class PostSearchBox extends SearchBox
{
    public string $placeholder = 'Search posts...';

    protected function input(): SearchInput
    {
        return new PostSearchInput(['placeholder' => $this -> placeholder]);
    }
}
