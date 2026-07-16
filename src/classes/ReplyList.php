<?php

declare(strict_types=1);

/**
 * The list of reply posts under a post, fetched straight into its contents at
 * construction. main.js locates it by its class name to insert newly posted
 * replies at the top, and grows it by infinite scroll off the data-* here.
 * Build with the post whose replies these are: new ReplyList(['parentId' => 5]).
 */
class ReplyList extends Div
{
    public const PAGE_SIZE = 20;

    public ?string $class = 'ReplyList d-flex flex-column gap-4';

    public ?int $parentId = null;
    public bool $hasMore = false;

    public function __construct(array|object|null $properties = null)
    {
        parent::__construct($properties);

        ['rows' => $rows, 'hasMore' => $this -> hasMore] = Post::replyRows((int) $this -> parentId, self::PAGE_SIZE);
        $this -> contents = Post::withItemsAndCounts($rows);
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
        if ($this -> parentId !== null) {
            $this -> attributes['data-parent-id'] = (string) $this -> parentId;
        }

        if ($this -> contents !== []) {
            $this -> attributes['data-oldest-post-id'] = (string) $this -> contents[count($this -> contents) - 1] -> postId;
        }

        $this -> attributes['data-has-more'] = $this -> hasMore ? '1' : '0';

        return parent::toDOM();
    }
}
