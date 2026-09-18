<?php

declare(strict_types=1);

/**
 * The columns FeedItem::itemsForPosts() reads off a FeedItems row.
 * The type column selects the concrete FeedItem subclass before its inherited
 * constructor hydrates these fields. Rendering identity is excluded by glom().
 */
class FeedItemData
{
    public ?int $itemId = null;
    public ?int $postId = null;
    public ?string $type = null;
    public ?string $createdAt = null;
    public ?string $remoteURL = null;
    public ?string $altText = null;
}
