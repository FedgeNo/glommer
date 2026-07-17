<?php

declare(strict_types=1);

/**
 * A page of feed posts, fetched straight into its own contents at construction
 * and grown by infinite scroll (main.js) off the data-* attributes toDOM sets.
 * Which feed is chosen by feedType: 'global' (the default), 'friends' (the
 * viewer's timeline, needs userId), 'user' (one profile's posts, needs userId),
 * or 'tag' (posts under a #tag, needs tag). Cursored on postId with a sentinel
 * above any real id, so page one and a load-more page are one query. Build with
 * the properties for the feed you want, e.g.
 * new FeedList(['feedType' => 'user', 'userId' => 42]).
 */
class FeedList extends ItemList
{
    public const PAGE_SIZE = 20;

    public ?string $class = 'FeedList d-flex flex-column gap-4';

    public ?string $feedType = null;
    public ?int $userId = null;
    public ?string $tag = null;
    public ?int $before = null;

    public function __construct(array|object|null $properties = null)
    {
        parent::__construct($properties);

        $this -> contents = Post::withItemsAndCounts($this -> fetchRows());
    }

    /**
     * @return Post[]
     */
    private function fetchRows(): array
    {
        $not_banned = 0;
        $cursor = $this -> before ?? PHP_INT_MAX;
        $limit = self::PAGE_SIZE + 1;

        return match ($this -> feedType) {
            'friends' => DB::rows('
SELECT `Posts`.*
    FROM `Timelines`
    JOIN `Posts` ON `Posts`.`postId` = `Timelines`.`postId`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Timelines`.`userId` = ? AND `Users`.`banned` = ? AND `Timelines`.`postId` < ?
    ORDER BY `Timelines`.`postId` DESC
    LIMIT ?
', 'Post', 'iiii', (int) $this -> userId, $not_banned, $cursor, $limit),

            'user' => DB::rows('
SELECT `Posts`.*
    FROM `Posts`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Posts`.`parentId` IS NULL AND `Posts`.`userId` = ? AND `Users`.`banned` = ? AND `Posts`.`postId` < ?
    ORDER BY `Posts`.`postId` DESC
    LIMIT ?
', 'Post', 'iiii', (int) $this -> userId, $not_banned, $cursor, $limit),

            'tag' => DB::rows('
SELECT `Posts`.*
    FROM `PostHashtags`
    JOIN `Hashtags` ON `Hashtags`.`hashtagId` = `PostHashtags`.`hashtagId`
    JOIN `Posts` ON `Posts`.`postId` = `PostHashtags`.`postId`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Hashtags`.`slug` = ? AND `Posts`.`parentId` IS NULL AND `Users`.`banned` = ? AND `Posts`.`postId` < ?
    ORDER BY `Posts`.`postId` DESC
    LIMIT ?
', 'Post', 'siii', (string) $this -> tag, $not_banned, $cursor, $limit),

            default => DB::rows('
SELECT `Posts`.*
    FROM `Posts`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Posts`.`parentId` IS NULL AND `Users`.`banned` = ? AND `Posts`.`postId` < ?
    ORDER BY `Posts`.`postId` DESC
    LIMIT ?
', 'Post', 'iii', $not_banned, $cursor, $limit),
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
        $has_more = count($this -> contents) > self::PAGE_SIZE;

        if ($has_more) {
            array_pop($this -> contents);
        }

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

        $this -> attributes['data-has-more'] = $has_more ? '1' : '0';

        return parent::toDOM();
    }
}
