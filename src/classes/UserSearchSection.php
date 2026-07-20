<?php

declare(strict_types=1);

/**
 * The titled results area of a UserSearch. The client retitles the heading once
 * a query replaces the suggestions (see main.js), so it stands even when the
 * list underneath is empty.
 */
class UserSearchSection extends UserSection
{
    public ?string $class = 'UserSearchSection';

    protected string $heading = 'Suggested Users';

    public int $viewerId = 0;

    protected function headsEmptyList(): bool
    {
        return true;
    }

    protected function list(): ItemLoader
    {
        return new UserSearchList(['viewerId' => $this -> viewerId, 'offset' => $this -> offset]);
    }
}
