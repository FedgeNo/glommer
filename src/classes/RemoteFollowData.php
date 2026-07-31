<?php

declare(strict_types=1);

/**
 * The columns RemoteFollow's queries read off a RemoteFollows row. Some fetch
 * only a subset of these - the rest just stay null.
 */
class RemoteFollowData
{
    public ?int $remoteFollowId = null;
    public ?int $localUserId = null;
    public ?string $remoteActorURI = null;
    public ?string $status = null;
    public ?string $followActivityId = null;
    public ?string $createdAt = null;
}
