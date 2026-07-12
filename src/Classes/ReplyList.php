<?php

declare(strict_types=1);

/**
 * The list of reply Threads under a post. main.js locates it by its class
 * name to insert newly posted replies at the top.
 */
class ReplyList extends Div
{
    public ?string $class = 'ReplyList d-flex flex-column gap-4';

    public ?int $parentId = null;
    public ?int $oldestPostId = null;
    public bool $hasMore = false;

    public function toDOM(): \DOMElement
    {
        if ($this -> parentId !== null) {
            $this -> attributes['data-parent-id'] = (string) $this -> parentId;
        }

        if ($this -> oldestPostId !== null) {
            $this -> attributes['data-oldest-post-id'] = (string) $this -> oldestPostId;
        }

        $this -> attributes['data-has-more'] = $this -> hasMore ? '1' : '0';

        return parent::toDOM();
    }

    /**
     * @param array[] $rows reply Posts rows (newest first), each becoming a Thread
     */
    public static function fromRows(int $parent_id, array $rows, bool $has_more): self
    {
        $list = new self();
        $list -> parentId = $parent_id;

        if ($rows === []) {
            return $list;
        }

        $list -> oldestPostId = (int) $rows[count($rows) - 1]['postId'];
        $list -> hasMore = $has_more;

        foreach (Thread::fromRows($rows) as $thread) {
            $list -> addContents($thread);
        }

        return $list;
    }
}
