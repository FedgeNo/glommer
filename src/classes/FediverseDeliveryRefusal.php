<?php

declare(strict_types=1);

/**
 * What a remote server said when it turned an activity away.
 *
 * Only refusals are kept. A delivery that arrived is already counted and its
 * response says nothing anybody needs; a delivery that was refused carries its
 * only explanation in a body that is otherwise thrown away, and the queue row
 * that might have held the reason is deleted whether the activity arrived or
 * was given up on. The practical effect of not keeping this is that an
 * activity the far side rejected looks exactly like one it accepted, so every
 * question about federation working has to be answered by guessing.
 */
class FediverseDeliveryRefusal
{
    /** Long enough to find a pattern, short enough that a diagnostic log is not a data store. */
    public const KEEP_DAYS = 30;

    /**
     * @param array<string, mixed> $activity
     */
    public static function record(string $inbox_url, array $activity, ?int $status, ?string $body): void
    {
        $type = is_string($activity['type'] ?? null) ? mb_substr($activity['type'], 0, 64) : null;
        $uri = is_string($activity['id'] ?? null) ? mb_substr($activity['id'], 0, 255) : null;
        $trimmed = $body === null || trim($body) === '' ? null : mb_substr(trim($body), 0, 500);

        DB::run('
INSERT INTO `FediverseDeliveryRefusals` (`inboxURL`, `activityType`, `activityURI`, `status`, `body`)
    VALUES (?, ?, ?, ?, ?)
', 'sssis', mb_substr($inbox_url, 0, 255), $type, $uri, $status, $trimmed);
    }

    /** Drops what is too old to be telling anybody anything. */
    public static function prune(): void
    {
        DB::run('
DELETE FROM `FediverseDeliveryRefusals`
    WHERE `createdAt` < NOW() - INTERVAL ? DAY
', 'i', self::KEEP_DAYS);
    }

    /**
     * The most recent refusals, newest first - what the admin services panel
     * reports and what answers "did that actually go out".
     *
     * @return self[]
     */
    public static function recent(int $limit = 20): array
    {
        return DB::rows('
SELECT *
    FROM `FediverseDeliveryRefusals`
    ORDER BY `refusalId` DESC
    LIMIT ?
', self::class, 'i', $limit);
    }
}
