<?php

declare(strict_types=1);

/**
 * What reporting a remote post to its own server takes: the object being
 * reported, the account it is filed against, and where to deliver it.
 */
class RemoteFlagTargetData
{
    public ?string $remoteObjectURI = null;
    public ?string $remoteActorURI = null;
    public ?string $remoteActorInboxURL = null;
}
