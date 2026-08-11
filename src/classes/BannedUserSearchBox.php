<?php

declare(strict_types=1);

class BannedUserSearchBox extends SearchBox
{
    // Read at render rather than set as a default: a property initializer runs
    // before there is a request to have a locale, so it would freeze whichever
    // language the first call happened to want.
    public string $placeholder = '';

    protected function input(): SearchInput
    {
        $placeholder = $this -> placeholder !== ''
            ? $this -> placeholder
            : (string) (Strings::for(self::class)['placeholder'] ?? '');

        return new BannedUserSearchInput(['placeholder' => $placeholder]);
    }
}
