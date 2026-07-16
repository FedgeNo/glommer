<?php

declare(strict_types=1);

/**
 * The columns RememberToken's queries read off a RememberTokens row - never
 * exposed outside RememberToken/RememberedDevicesList; the validator itself
 * never leaves the server.
 */
class RememberTokenData
{
    public ?int $tokenId = null;
    public ?int $userId = null;
    public ?string $selector = null;
    public ?string $validatorHash = null;
    public ?string $createdAt = null;
    public ?string $lastUsedAt = null;
    public ?string $userAgent = null;
    public ?string $ipAddress = null;
}
