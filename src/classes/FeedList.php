<?php

declare(strict_types=1);

/**
 * A page of feed posts, grown by infinite scroll off the scroll config here -
 * the client asks for the next page. Each kind of feed is its own subclass
 * with its own query; feedType names it for api/feed.php so it knows which
 * query to run.
 */
abstract class FeedList extends ItemList
{
    public ?string $class = 'FeedList';

    /** Names this feed to api/feed.php, which picks its query from it. */
    protected string $feedType = '';

    /**
     * Everything the next-page request carries besides the offset the client
     * counts for itself. InfiniteScroller sends every key here except
     * endpoint/itemType/direction, so a feed selected by more than its type
     * (one profile's posts, one tag's) adds that field here under exactly the
     * name api/feed.php reads it by. Null for a feed whose paging the client
     * drives itself.
     *
     * @return array<string, mixed>|null
     */
    protected function scrollConfig(): ?array
    {
        $config = [
            'endpoint' => '/api/feed',
            'itemType' => 'Post',
            'feedType' => $this -> feedType,
        ];

        $cursor = $this -> cursor();

        return $cursor === null ? $config : $config + ['cursor' => $cursor];
    }

    /** @return array<string, int|string>|null */
    protected function cursor(): ?array
    {
        return null;
    }

    public function toJSON(): array
    {
        return parent::toJSON() + ['cursor' => $this -> cursor()];
    }

    /**
     * @return array<string, string>
     */
    protected function dataAttributes(): array
    {
        $scroll_config = $this -> scrollConfig();

        if ($scroll_config === null) {
            return [];
        }

        return ['data-infinite-scroll' => (string) json_encode($scroll_config)];
    }
}
