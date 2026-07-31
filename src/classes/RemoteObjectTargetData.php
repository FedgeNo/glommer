<?php

declare(strict_types=1);

/**
 * Where a post that came from elsewhere lives, and where to send its server a
 * reaction to it.
 */
class RemoteObjectTargetData
{
    public ?string $remoteObjectURI = null;
    public ?string $remoteActorInboxURL = null;
}
