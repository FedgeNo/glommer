<?php

declare(strict_types=1);

/**
 * The titled results area of a UserSearch. The client retitles the heading once
 * a query replaces the suggestions (see main.js), so it stands even when the
 * list underneath is empty.
 */
class UserSearchSection extends UserSection
{
    protected string $heading = 'Suggested Users';

    protected bool $headsEmptyList = true;


    protected function list(): ItemLoader
    {
        return new UserSearchList(['offset' => $this -> offset]);
    }
}
