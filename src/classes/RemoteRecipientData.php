<?php

declare(strict_types=1);

/**
 * The columns identifying a remote account a post is addressed to - its handle
 * for the Mention tag, its actor URI for the audience, and its inbox for the
 * delivery.
 */
class RemoteRecipientData
{
    public ?string $slug = null;
    public ?string $remoteActorURI = null;
    public ?string $remoteActorInboxURL = null;
}
