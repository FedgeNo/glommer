<?php

declare(strict_types=1);

/**
 * The posts a member has pinned, shown above their ordinary posts.
 *
 * An ordinary loader whose page is simply never full: the pin cap is far under
 * a page, so hasMore can never trip and no paging config is ever offered.
 */
class PinnedPostList extends FeedList
{
    public ?string $class = 'PinnedPostList';

    public ?int $userId = null;

    protected function rows(): array
    {
        return PinnedPost::postsFor((int) $this -> userId);
    }

    /** Complete by construction - there is no next page to configure. */
    protected function scrollConfig(): ?array
    {
        return null;
    }
}
