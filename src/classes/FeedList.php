<?php

declare(strict_types=1);

/**
 * A page of feed posts, fetched straight into its own contents at construction
 * and grown by infinite scroll (main.js) off the data-* attributes toDOM sets.
 * Which feed is chosen by feedType: 'global' (the default), 'friends' (the
 * viewer's timeline), 'user' (one profile's posts, needs userId), or 'tag'
 * (posts under a #tag, needs tag). Build with the properties for the feed you
 * want, e.g. new FeedList(['feedType' => 'user', 'userId' => 42]).
 */
class FeedList extends Div
{
    public const PAGE_SIZE = 20;

    public ?string $class = 'FeedList d-flex flex-column gap-4';

    public ?string $feedType = null;
    public ?int $userId = null;
    public ?string $tag = null;
    public bool $hasMore = false;

    public function __construct(array|object|null $properties = null)
    {
        parent::__construct($properties);

        ['rows' => $rows, 'hasMore' => $this -> hasMore] = $this -> fetchRows();
        $this -> contents = Post::withItemsAndCounts($rows);
    }

    /**
     * @return array{rows: Post[], hasMore: bool}
     */
    private function fetchRows(): array
    {
        return match ($this -> feedType) {
            'friends' => Timeline::rowsForUser((int) $this -> userId, self::PAGE_SIZE),
            'user' => Post::userFeedRows((int) $this -> userId, self::PAGE_SIZE),
            'tag' => Hashtag::postRows((string) $this -> tag, self::PAGE_SIZE),
            default => Post::globalFeedRows(self::PAGE_SIZE),
        };
    }

    /**
     * Whether the feed came back with any posts - lets a page decide before
     * rendering whether to 404 (an empty tag) or hide the sibling controls it
     * would otherwise wrap this in (a profile with no posts).
     */
    public function hasItems(): bool
    {
        return $this -> contents !== [];
    }

    public function toDOM(): \DOMElement
    {
        if ($this -> feedType !== null) {
            $this -> attributes['data-feed-type'] = $this -> feedType;
        }

        if ($this -> userId !== null) {
            $this -> attributes['data-user-id'] = (string) $this -> userId;
        }

        if ($this -> tag !== null) {
            $this -> attributes['data-tag'] = $this -> tag;
        }

        if ($this -> contents !== []) {
            $this -> attributes['data-oldest-post-id'] = (string) $this -> contents[count($this -> contents) - 1] -> postId;
        }

        $this -> attributes['data-has-more'] = $this -> hasMore ? '1' : '0';

        return parent::toDOM();
    }
}
