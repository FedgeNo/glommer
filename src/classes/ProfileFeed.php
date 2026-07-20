<?php

declare(strict_types=1);

/**
 * A profile's "Posts" section: the heading over that user's own post feed. Owns
 * the FeedList it wraps (feedType 'user'), so a page builds one from a user id
 * and asks hasItems() to decide whether to show it - and the post-search box
 * that sits above it - at all. An empty profile shows neither.
 */
class ProfileFeed extends Section
{
    public ?string $class = 'ProfileFeed';

    public ?int $userId = null;

    private ProfileFeedList $feed;

    public function __construct(array|object|null $properties = null)
    {
        parent::__construct($properties);

        $this -> feed = new ProfileFeedList(['userId' => $this -> userId]);
    }

    public function hasItems(): bool
    {
        return $this -> feed -> hasItems();
    }

    public function toDOM(): \DOMElement
    {
        $this -> contents[] = new Heading2('Posts');
        $this -> contents[] = $this -> feed;

        return parent::toDOM();
    }
}
