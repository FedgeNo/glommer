<?php

declare(strict_types=1);

/**
 * A profile's "Posts" section: the heading over that user's own posts.
 */
class ProfileFeedSection extends ListSection
{
    public ?string $class = 'ProfileFeedSection';

    protected string $heading = 'Posts';

    public ?int $userId = null;
    public int $offset = 0;

    protected function list(): ItemLoader
    {
        return new ProfileFeedList(['userId' => $this -> userId, 'offset' => $this -> offset]);
    }
}
