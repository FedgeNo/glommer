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
class RememberedDevicesList extends ItemList
{
    public ?string $class = 'd-flex flex-column RememberedDevicesList';

    /** This query has no LIMIT, so every row it returns is kept. */
    public const PAGE_SIZE = PHP_INT_MAX;

    protected string $emptyNotice = 'No remembered devices. Devices where you check "Remember me" at login appear here.';

    public int $userId = 0;

    protected function rows(): array
    {
        return RememberToken::rowsForUser($this -> userId);
    }
}
