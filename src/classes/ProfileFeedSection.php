<?php

declare(strict_types=1);

/**
 * A profile's "Posts" section: the heading over that user's own posts, and the
 * results of searching them.
 *
 * Both lists live under the one heading, which names whichever is showing (see
 * main.js). The search list starts empty; the client fills it from
 * api/search-posts.php as the person types, and the posts give way to it.
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
