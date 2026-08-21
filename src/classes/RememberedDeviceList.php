<?php

declare(strict_types=1);

/**
 * The Settings "Remembered devices" section: every persistent "Remember me"
 * login still active for this user, so they can spot one they don't
 * recognise and revoke it. A login where "Remember me" was NOT checked
 * leaves no persistent token and so isn't listed - it's a session that dies
 * with the browser, never a standing device. The current browser's own
 * remembered token (if any) is marked and left un-revokable here (see
 * RememberedDevice).
 */
class RememberedDeviceList extends ItemList
{
    public ?string $class = 'RememberedDeviceList';

    protected string $emptyNotice = '';

    public function __construct(array|object|null $properties = null)
    {
        $this -> emptyNotice = (string) (Strings::for(self::class)['emptyNotice'] ?? '');
        parent::__construct($properties);
    }

    public int $userId = 0;

    protected function rows(): array
    {
        return DB::rows('
SELECT `tokenId`, `selector`, `createdAt`, `lastUsedAt`, `userAgent`, `ipAddress`
    FROM `RememberTokens`
    WHERE `userId` = ? AND `expiresAt` > NOW() AND `consumedAt` IS NULL
    ORDER BY `lastUsedAt` DESC
    LIMIT ? OFFSET ?
', 'RememberedDevice', 'iii', $this -> userId, static::PAGE_SIZE + 1, $this -> offset);
    }
}
