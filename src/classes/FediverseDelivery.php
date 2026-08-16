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
     * How long a claimed row stays a worker's alone. Comfortably longer than a
     * whole batch of slow deliveries, and short enough that a worker dying
     * mid-batch only delays its rows by minutes.
     */
    public const CLAIM_SECONDS = 600;

    /**
     * Queues one activity for every inbox given. Encoding happens once, here,
     * so the worker sends the same bytes to every destination and a payload
     * that cannot be encoded fails at the point it was built rather than
     * silently later.
     *
     * A null actor means the instance signs it rather than a member - the one
     * case being a Flag, where naming who reported would hand a harasser their
     * identity.
     *
     * @param array<string, mixed> $activity
     * @param string[] $inbox_urls
     */
    public static function enqueue(?int $actor_user_id, array $activity, array $inbox_urls): void
    {
        $body = json_encode($activity, JSON_UNESCAPED_SLASHES);

        if ($body === false || $inbox_urls === []) {
            return;
        }

        foreach (array_unique($inbox_urls) as $inbox_url) {
            // Nothing is queued for a server already blocked, so the queue
            // doesn't fill with rows whose only future is to be refused. A
            // domain blocked after the fact is covered twice over: blocking
            // deletes what is queued for it, and SafeHTTPFetcher refuses the
            // send regardless.
            if (RemoteServer::isBlockedURL($inbox_url)) {
                continue;
            }

            DB::run('
INSERT INTO `FediverseDeliveries` (`actorUserId`, `inboxURL`, `activity`)
    VALUES (?, ?, ?)
', 'iss', $actor_user_id, $inbox_url, $body);
        }
    }

    /**
     * The next rows that are due, claimed for this worker alone. Ordered by
     * when they became due so a retry that has waited longest goes first,
     * which keeps one unreachable server from starving everything queued
     * behind it.
     *
     * Claimed, not just read: the batch is stamped with an expiring lease
     * inside one transaction, with SKIP LOCKED keeping two workers out of
     * each other's rows, so overlapping workers never send the same activity
     * twice. The lease lapses on its own, so a worker that dies mid-batch
     * only delays its rows rather than stranding them.
     *
     * @return FediverseDeliveryData[]
     */
    public static function due(): array
    {
        $limit = self::BATCH_SIZE;
        $lease_seconds = self::CLAIM_SECONDS;

        return DB::transaction(static function () use ($limit, $lease_seconds): array {
            $rows = DB::rows('
SELECT *
    FROM `FediverseDeliveries`
    WHERE `nextAttemptAt` <= NOW() AND (`claimedUntil` IS NULL OR `claimedUntil` <= NOW())
    ORDER BY `nextAttemptAt`, `deliveryId`
    LIMIT ?
    FOR UPDATE SKIP LOCKED
', 'FediverseDeliveryData', 'i', $limit);

            foreach ($rows as $row) {
                DB::run('
UPDATE `FediverseDeliveries`
    SET `claimedUntil` = NOW() + INTERVAL ? SECOND
    WHERE `deliveryId` = ?
', 'ii', $lease_seconds, $row -> deliveryId);
            }

            return $rows;
        });
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
     *
     * @return bool true when that was the last try and the activity is never
     *              going out - the caller counts that, since giving up and
     *              arriving both end with the row deleted and only the caller
     *              knows which happened
     */
    public static function failed(int $delivery_id, int $attempts, string $error): bool
    {
        if ($attempts + 1 >= self::MAX_ATTEMPTS) {
            self::succeeded($delivery_id);

            return true;
        }

        $delay_seconds = 60 * (2 ** min($attempts, 10));

        // Cut by character rather than by byte: the message is whatever a
        // remote server said, and half a UTF-8 sequence is not storable in a
        // utf8mb4 column. The claim is released with the reschedule, so the
        // retry is anyone's to take when it comes due.
        DB::run('
UPDATE `FediverseDeliveries`
    SET `attempts` = `attempts` + 1, `nextAttemptAt` = NOW() + INTERVAL ? SECOND, `lastError` = ?, `claimedUntil` = NULL
    WHERE `deliveryId` = ?
', 'isi', $delay_seconds, mb_substr($error, 0, 255), $delivery_id);

        return false;
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
        if ($author -> userId === null || $author -> remoteActorURI !== null || (int) $author -> banned === 1) {
            return;
        }

        // Subscribed relays are fed exactly what a follower is: a relay stands
        // in for a crowd of them, and half of what subscribing to one buys is
        // that this server's public posts reach everyone else subscribed.
        $inboxes = array_filter(
            array_merge(FediverseFollower::deliveryInboxesFor((int) $author -> userId), Relay::deliveryInboxes(), $also),
            static fn (string $inbox): bool => $inbox !== '' && !ActivityPubActor::isLocalActorURI($inbox)
        );

        self::enqueue((int) $author -> userId, $activity, $inboxes);
    }
}
