<?php

declare(strict_types=1);

/**
 * The titled results area of a FriendSearch. It stands even while empty so the
 * client has somewhere to put the matches for the current query (see main.js).
 */
class FriendSearchSection extends UserSection
{
    protected string $heading = 'Search Results';

    protected bool $headsEmptyList = true;

    protected function list(): ItemLoader
    {
        return new FriendSearchList(['user' => $this -> user, 'offset' => $this -> offset]);
    }
}
