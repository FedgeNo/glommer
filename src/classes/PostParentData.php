<?php

declare(strict_types=1);

/**
 * What identifying a post's parent takes: its id and author slug to build our
 * own permalink, or the remote URI it already had if it came from elsewhere.
 */
class PostParentData
{
    public ?int $postId = null;
    public ?string $remoteObjectURI = null;
    public ?string $slug = null;
}
