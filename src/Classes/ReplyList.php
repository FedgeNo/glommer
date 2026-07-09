<?php

declare(strict_types=1);

/**
 * The list of reply Threads under a post. main.js locates it by its class
 * name to insert newly posted replies at the top.
 */
class ReplyList extends Div
{
    public ?string $class = 'ReplyList d-flex flex-column gap-4';

    /**
     * @param array[] $rows reply Posts rows, each becoming a Thread
     */
    public static function fromRows(array $rows): self
    {
        $list = new self();

        foreach (Thread::fromRows($rows) as $thread) {
            $list -> addContents($thread);
        }

        return $list;
    }
}
