<?php

declare(strict_types=1);

/**
 * The columns FeedItem::itemsForPosts() reads off a FeedItems row.
 * Deliberately not an HTMLObject descendant like FeedItem itself - copying
 * this onto a freshly-constructed FeedItem subclass must never touch that
 * subclass's own constructor-computed $class/$tagName.
 */
class FeedItemData
{
    public ?int $itemId = null;
    public ?int $postId = null;
    public ?string $type = null;
    public ?string $createdAt = null;
}
