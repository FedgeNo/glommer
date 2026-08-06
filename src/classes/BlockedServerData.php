<?php

declare(strict_types=1);

/** The columns read off a BlockedServers row. */
class BlockedServerData
{
    public ?string $domain = null;
    public ?string $reason = null;
    public ?int $blockedBy = null;
    public ?string $createdAt = null;
}
