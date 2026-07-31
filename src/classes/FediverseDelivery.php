<?php

declare(strict_types=1);

/**
 * The queue of activities waiting to reach remote inboxes, and the retry policy
 * behind it.
 *
 * Nothing is delivered inside the request that caused it. Someone with a
 * thousand followers posting is a thousand HTTPS round trips to servers that
 * may be slow, down, or gone, and none of that belongs between pressing Post
 * and seeing the post. bin/federation-worker.php drains this.
 *
 * Retries back off on powers of two and then stop. A server that has refused a
 * dozen times across several days is not going to accept this particular
 * activity, and a queue that never gives up eventually becomes a queue that
 * never drains.
 */
class FediverseDelivery
{
    /** After this many failures the row is dropped rather than retried again. */
    public const MAX_ATTEMPTS = 12;

    /** How many rows one pass of the worker takes at a time. */
    public const BATCH_SIZE = 20;

    /**
     * Queues one activity for every inbox given. Encoding happens once, here,
     * so the worker sends the same bytes to every destination and a payload
     * that cannot be encoded fails at the point it was built rather than
     * silently later.
     *
     * @param array<string, mixed> $activity
     * @param string[] $inbox_urls
     */
    public static function enqueue(int $actor_user_id, array $activity, array $inbox_urls): void
    {
        $body = json_encode($activity, JSON_UNESCAPED_SLASHES);

        if ($body === false || $inbox_urls === []) {
            return;
        }

        foreach (array_unique($inbox_urls) as $inbox_url) {
            // Checked at queue time and again nowhere else: a domain blocked
            // after something was queued for it still gets skipped, because the
            // worker checks too.
            if (BlockedDomain::blocksURL($inbox_url)) {
                continue;
            }

            DB::run('
INSERT INTO `FediverseDeliveries` (`actorUserId`, `inboxURL`, `activity`)
    VALUES (?, ?, ?)
', 'iss', $actor_user_id, $inbox_url, $body);
        }
    }

    /**
     * The next rows that are due. Ordered by when they became due so a retry
     * that has waited longest goes first, which keeps one unreachable server
     * from starving everything queued behind it.
     *
     * @return FediverseDeliveryData[]
     */
    public static function due(): array
    {
        $limit = self::BATCH_SIZE;

        return DB::rows('
SELECT *
    FROM `FediverseDeliveries`
    WHERE `nextAttemptAt` <= NOW()
    ORDER BY `nextAttemptAt`, `deliveryId`
    LIMIT ?
', 'FediverseDeliveryData', 'i', $limit);
    }

    public static function succeeded(int $delivery_id): void
    {
        DB::run('
DELETE FROM `FediverseDeliveries`
    WHERE `deliveryId` = ?
', 'i', $delivery_id);
    }

    /**
     * Records a failure and schedules the retry, or gives up. The delay
     * doubles - a minute, two, four - so a server that is briefly down is
     * retried quickly and one that is properly gone is left alone.
     */
    public static function failed(int $delivery_id, int $attempts, string $error): void
    {
        if ($attempts + 1 >= self::MAX_ATTEMPTS) {
            self::succeeded($delivery_id);

            return;
        }

        $delay_seconds = 60 * (2 ** min($attempts, 10));
        $trimmed = substr($error, 0, 255);

        DB::run('
UPDATE `FediverseDeliveries`
    SET `attempts` = `attempts` + 1, `nextAttemptAt` = NOW() + INTERVAL ? SECOND, `lastError` = ?
    WHERE `deliveryId` = ?
', 'isi', $delay_seconds, $trimmed, $delivery_id);
    }

    /** How much is waiting - for the admin services panel to report. */
    public static function pendingCount(): int
    {
        $row = DB::row('
SELECT COUNT(*) AS `total`
    FROM `FediverseDeliveries`
', 'PostCountData');

        return $row === null ? 0 : (int) $row -> total;
    }

    /**
     * Queues an activity to everyone following this member. The author's own
     * server is never a destination: a member here is read directly, and a
     * federated copy on top would be the same post twice.
     *
     * $also carries inboxes that are not followers but are part of the
     * audience anyway - the author of a post being replied to, someone
     * mentioned. Without them a reply reaches everyone except the person it
     * answers.
     *
     * @param array<string, mixed> $activity
     * @param string[] $also
     */
    public static function fanOut(User $author, array $activity, array $also = []): void
    {
        // A banned member's actor stops resolving, so anything sent on their
        // behalf would be unverifiable at the far end anyway - and continuing to
        // publish for someone this server has stopped hosting is wrong however
        // it is received.
        if ($author -> userId === null || !ActivityPubActor::isLocal($author) || (int) $author -> banned === 1) {
            return;
        }

        $inboxes = array_filter(
            array_merge(FediverseFollower::deliveryInboxesFor((int) $author -> userId), $also),
            static fn (string $inbox): bool => $inbox !== '' && !ActivityPubActor::isLocalActorURI($inbox)
        );

        self::enqueue((int) $author -> userId, $activity, $inboxes);
    }
}
