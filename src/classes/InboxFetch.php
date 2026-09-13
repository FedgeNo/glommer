<?php

declare(strict_types=1);

/** Authenticated Creates needing an object or thread read by the worker. */
class InboxFetch
{
    public const MAX_PENDING = 5000;
    public const MAX_ATTEMPTS = 12;
    public const BATCH_SIZE = 20;

    // Claimed one at a time. A thread may require thirty posts and their actors,
    // each with up to four bounded HTTP requests including redirects.
    public const CLAIM_SECONDS = 1800;

    public ?int $inboxFetchId = null;
    public ?string $activityHash = null;
    public ?string $actorURI = null;
    public ?string $objectURI = null;
    public ?string $activity = null;
    public ?int $attempts = null;
    public ?string $nextAttemptAt = null;
    public ?string $claimedUntil = null;
    public ?string $createdAt = null;

    public static function enqueue(array $activity, string $actor_uri, string $object_uri): void
    {
        $body = json_encode($activity, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        if (strlen($body) > 262144 || strlen($actor_uri) > 255 || strlen($object_uri) > 255) {
            throw new \LengthException('Deferred ActivityPub delivery exceeds its storage limit');
        }

        $hash = hash('sha256', $actor_uri . "\n" . $body);

        if (DB::row('SELECT `inboxFetchId` FROM `InboxFetches` WHERE `activityHash` = ?', self::class, 's', $hash) !== null) {
            return;
        }

        // An authenticated delivery must not be acknowledged and silently shed
        // like a relay hint. A full queue fails the request so the sender retries.
        if (self::pendingCount() >= self::MAX_PENDING) {
            throw new \RuntimeException('The ActivityPub inbox fetch queue is full');
        }

        try {
            DB::run('
INSERT INTO `InboxFetches` (`activityHash`, `actorURI`, `objectURI`, `activity`)
    VALUES (?, ?, ?, ?)
', 'ssss', $hash, $actor_uri, $object_uri, $body);
        } catch (\mysqli_sql_exception $exception) {
            if ($exception -> getCode() !== 1062) {
                throw $exception;
            }
        }
    }

    public static function claim(): ?self
    {
        return DB::transaction(static function (): ?self {
            $row = DB::row('
SELECT *
    FROM `InboxFetches`
    WHERE `nextAttemptAt` <= NOW() AND (`claimedUntil` IS NULL OR `claimedUntil` <= NOW())
    ORDER BY `nextAttemptAt`, `inboxFetchId`
    LIMIT 1
    FOR UPDATE SKIP LOCKED
', self::class);

            if ($row !== null) {
                DB::run('
UPDATE `InboxFetches`
    SET `claimedUntil` = NOW() + INTERVAL ? SECOND
    WHERE `inboxFetchId` = ?
', 'ii', self::CLAIM_SECONDS, $row -> inboxFetchId);
            }

            return $row;
        });
    }

    public static function done(int $id): void
    {
        DB::run('DELETE FROM `InboxFetches` WHERE `inboxFetchId` = ?', 'i', $id);
    }

    public static function failed(int $id, int $attempts): void
    {
        if ($attempts + 1 >= self::MAX_ATTEMPTS) {
            error_log('Giving up deferred ActivityPub delivery ' . $id . ' after ' . self::MAX_ATTEMPTS . ' attempts');
            self::done($id);

            return;
        }

        $delay = min(86400, 60 * (2 ** $attempts));

        DB::run('
UPDATE `InboxFetches`
    SET `attempts` = `attempts` + 1, `nextAttemptAt` = NOW() + INTERVAL ? SECOND, `claimedUntil` = NULL
    WHERE `inboxFetchId` = ?
', 'ii', $delay, $id);
    }

    public static function cancel(string $actor_uri, string $object_uri): void
    {
        DB::run('DELETE FROM `InboxFetches` WHERE `actorURI` = ? AND `objectURI` = ?', 'ss', $actor_uri, $object_uri);
    }

    public static function pendingCount(): int
    {
        $row = DB::row('SELECT COUNT(*) AS `total` FROM `InboxFetches`', 'PostCountData');

        return (int) ($row -> total ?? 0);
    }
}
