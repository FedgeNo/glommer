<?php

declare(strict_types=1);

/**
 * A page of feed posts, grown by infinite scroll (main.js) off the data-*
 * attributes here - the client asks for the next page by saying how many posts
 * it already shows. Each kind of feed is its own subclass with its own query;
 * feedType names it for the client so the scroll handler knows which endpoint
 * branch to ask.
 */
abstract class FeedList extends ItemList
{
    public ?string $class = 'FeedList d-flex flex-column';

    /** Names this feed to main.js and api/feed-history.php. */
    protected string $feedType = '';

    /**
     * @return array<string, string>
     */
    protected function dataAttributes(): array
    {
        return ['data-feed-type' => $this -> feedType];
    }
}
