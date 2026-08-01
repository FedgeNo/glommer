<?php

declare(strict_types=1);

/** The columns read off a PinnedPosts row. */
class PinnedPostData
{
    public ?int $userId = null;
    public ?int $postId = null;
    public ?string $createdAt = null;
}
