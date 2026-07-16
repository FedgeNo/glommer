<?php

declare(strict_types=1);

class FeedList extends Div
{
    public ?string $class = 'FeedList d-flex flex-column gap-4';

    public ?string $feedType = null;
    public ?int $userId = null;
    public ?string $tag = null;
    public ?int $oldestPostId = null;
    public bool $hasMore = false;

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

        if ($this -> oldestPostId !== null) {
            $this -> attributes['data-oldest-post-id'] = (string) $this -> oldestPostId;
        }

        $this -> attributes['data-has-more'] = $this -> hasMore ? '1' : '0';

        return parent::toDOM();
    }

    /**
     * @param Post[] $rows newest first.
     */
    public static function fromRows(string $feed_type, array $rows, bool $has_more, ?int $user_id = null): self
    {
        $list = new self();
        $list -> feedType = $feed_type;
        $list -> userId = $user_id;

        if ($rows === []) {
            return $list;
        }

        $list -> oldestPostId = (int) $rows[count($rows) - 1] -> postId;
        $list -> hasMore = $has_more;

        foreach (Thread::fromRows($rows) as $thread) {
            $list -> addContent($thread);
        }

        return $list;
    }
}
