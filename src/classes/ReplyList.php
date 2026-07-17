<?php

declare(strict_types=1);

/**
 * The list of reply posts under a post, fetched straight into its contents at
 * construction. main.js locates it by its class name to insert newly posted
 * replies at the top, and grows it by infinite scroll off the data-* here.
 * Cursored on postId with a sentinel above any real id, so page one and a
 * load-more page are one query. Build with the post whose replies these are:
 * new ReplyList(['parentId' => 5]).
 */
class ReplyList extends ItemList
{
    public const PAGE_SIZE = 20;

    public ?string $class = 'ReplyList d-flex flex-column';

    public ?int $parentId = null;
    public ?int $before = null;

    public function __construct(array|object|null $properties = null)
    {
        parent::__construct($properties);

        $not_banned = 0;
        $cursor = $this -> before ?? PHP_INT_MAX;

        $this -> contents = Post::withItemsAndCounts(DB::rows('
SELECT `Posts`.*
    FROM `Posts`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Posts`.`parentId` = ? AND `Users`.`banned` = ? AND `Posts`.`postId` < ?
    ORDER BY `Posts`.`postId` DESC
    LIMIT ?
', 'Post', 'iiii', (int) $this -> parentId, $not_banned, $cursor, self::PAGE_SIZE + 1));
    }

    /**
     * Whether the post has any replies - lets the page show the "Replies"
     * heading only when there's something under it.
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

        if ($this -> parentId !== null) {
            $this -> attributes['data-parent-id'] = (string) $this -> parentId;
        }

        if ($this -> contents !== []) {
            $this -> attributes['data-oldest-post-id'] = (string) $this -> contents[count($this -> contents) - 1] -> postId;
        }

        $this -> attributes['data-has-more'] = $has_more ? '1' : '0';

        return parent::toDOM();
    }
}
