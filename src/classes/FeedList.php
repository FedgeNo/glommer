<?php

declare(strict_types=1);

/**
 * A page of feed posts, fetched straight into its own contents at construction
 * and grown by infinite scroll (main.js) off the data-* attributes toDOM sets.
 * Which feed is chosen by feedType: 'global' (the default), 'friends' (the
 * viewer's timeline, needs userId), 'user' (one profile's posts, needs userId),
 * or 'tag' (posts under a #tag, needs tag). Paginated by offset: the client
 * asks for the next page by saying how many posts it already shows. Build with
 * the properties for the feed you want, e.g.
 * new FeedList(['feedType' => 'user', 'userId' => 42]).
 */
class FeedList extends ItemList
{
    public const PAGE_SIZE = 20;

    public ?string $class = 'FeedList d-flex flex-column';

    public ?string $feedType = null;
    public ?int $userId = null;
    public ?string $tag = null;
    public int $offset = 0;

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
        $limit = self::PAGE_SIZE + 1;

        return match ($this -> feedType) {
            'friends' => DB::rows('
SELECT `Posts`.*
    FROM `Timelines`
    JOIN `Posts` ON `Posts`.`postId` = `Timelines`.`postId`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Timelines`.`userId` = ? AND `Users`.`banned` = ?
    ORDER BY `Timelines`.`postId` DESC
    LIMIT ? OFFSET ?
', 'Post', 'iiii', (int) $this -> userId, $not_banned, $limit, $this -> offset),

            'user' => DB::rows('
SELECT `Posts`.*
    FROM `Posts`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Posts`.`parentId` IS NULL AND `Posts`.`userId` = ? AND `Users`.`banned` = ?
    ORDER BY `Posts`.`postId` DESC
    LIMIT ? OFFSET ?
', 'Post', 'iiii', (int) $this -> userId, $not_banned, $limit, $this -> offset),

            'tag' => DB::rows('
SELECT `Posts`.*
    FROM `PostHashtags`
    JOIN `Hashtags` ON `Hashtags`.`hashtagId` = `PostHashtags`.`hashtagId`
    JOIN `Posts` ON `Posts`.`postId` = `PostHashtags`.`postId`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Hashtags`.`slug` = ? AND `Posts`.`parentId` IS NULL AND `Users`.`banned` = ?
    ORDER BY `Posts`.`postId` DESC
    LIMIT ? OFFSET ?
', 'Post', 'siii', (string) $this -> tag, $not_banned, $limit, $this -> offset),

            // STRAIGHT_JOIN pins the join order to Posts first: it walks
            // parentId_postId backward and stops once the page is full. Left
            // to cost estimates, the optimizer drives from Users instead,
            // which collects and filesorts every non-banned author's
            // top-level posts to serve a 21-row page (measured ~270x slower
            // at 40k posts).
            default => DB::rows('
SELECT STRAIGHT_JOIN `Posts`.*
    FROM `Posts`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Posts`.`parentId` IS NULL AND `Users`.`banned` = ?
    ORDER BY `Posts`.`postId` DESC
    LIMIT ? OFFSET ?
', 'Post', 'iii', $not_banned, $limit, $this -> offset),
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

        $this -> attributes['data-has-more'] = $has_more ? '1' : '0';

        return parent::toDOM();
    }
}
