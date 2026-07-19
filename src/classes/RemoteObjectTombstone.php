<?php

declare(strict_types=1);

/**
 * Once a remote-origin post is gone - deleted by a moderator, removed as a
 * report resolution, or the origin server itself sent a real Delete activity
 * - its object URI is recorded here forever. ActivityPub delivery isn't
 * exactly-once, so the origin server redelivering the same Create later is
 * expected, not a bug; checked before every ingestion so a tombstoned object
 * can never be copied back in.
 */
class RemoteObjectTombstone
{
    public ?string $remoteObjectURI = null;
    public ?string $reason = null;
    public ?string $createdAt = null;

    public static function isTombstoned(string $remote_object_uri): bool
    {
        return DB::row('
SELECT *
    FROM `RemoteObjectTombstones`
    WHERE `remoteObjectURI` = ?
', self::class, 's', $remote_object_uri) !== null;
    }

    public static function tombstone(string $remote_object_uri, string $reason): void
    {
        DB::run('
INSERT INTO `RemoteObjectTombstones` (`remoteObjectURI`, `reason`)
    VALUES (?, ?)
    ON DUPLICATE KEY UPDATE `reason` = VALUES(`reason`)
', 'ss', $remote_object_uri, $reason);
    }
}
