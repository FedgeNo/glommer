<?php

declare(strict_types=1);

/**
 * The columns FediverseFollower's queries read off a FediverseFollowers row.
 * Some fetch only a subset of these - the rest just stay null.
 */
class FediverseFollowerData
{
    public ?int $fediverseFollowerId = null;
    public ?int $localUserId = null;
    public ?string $remoteActorURI = null;
    public ?string $inboxURL = null;
    public ?string $sharedInboxURL = null;
    public ?string $followActivityId = null;
    public ?string $createdAt = null;
}
