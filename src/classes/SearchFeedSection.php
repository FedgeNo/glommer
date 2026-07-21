<?php

declare(strict_types=1);

/**
 * The heading over what a post search turned up. Its list starts empty; the
 * client fills it from api/search-posts.php as the person types.
 *
 * Where a feed sits under the same search box, this section and that one take
 * turns: whichever is showing is the one the heading belongs to.
 */
class SearchFeedSection extends ListSection
{
    public ?string $class = 'SearchFeedSection';

    protected string $heading = 'Search Results';

    protected bool $headsEmptyList = true;

    public int $authorId = 0;

    protected function list(): ItemLoader
    {
        return new SearchFeedList(['authorId' => $this -> authorId]);
    }
}
