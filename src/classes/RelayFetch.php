<?php

declare(strict_types=1);

/**
 * The queue of posts this server has been told about and has yet to read.
 *
 * Two things put one here, and both are an address rather than a post. A relay
 * forwards what other servers published; and a reply arrives whose parent this
 * server has never seen, which is a thread to go and read the rest of.
 *
 * Either way the reading cannot happen where the address arrived. Two signed
 * fetches at five seconds each, redirects allowed, is tens of seconds of a PHP
 * worker held on somebody else's server; at the rate the inbox already permits,
 * that is enough concurrent workers to exhaust the pool and stop the site
 * answering at all. So the inbox writes down what to read and returns, and
 * bin/federation-worker.php does the reading.
 *
 * Unlike an outbound delivery, what is queued here is best-effort. Nobody is
 * waiting on any of it, so the queue is bounded and sheds new arrivals rather
 * than growing without limit, and a fetch that keeps failing is given up on
 * quickly - there is always more where it came from.
 */
class RelayFetch
{
    /**
     * How much may be waiting before new arrivals are dropped. A firehose can
     * name posts faster than they can be read, and a queue that grows without
     * limit is the same problem moved somewhere less visible.
     */
    public const MAX_PENDING = 5000;

    /** Two tries. A post that will not load is not worth chasing. */
    public const MAX_ATTEMPTS = 2;

    public const BATCH_SIZE = 20;

    /** Same claim lease as FediverseDelivery::CLAIM_SECONDS, same reasoning. */
    public const CLAIM_SECONDS = 600;

    // Declared so a row fetched via DB::row()/DB::rows() doesn't set them as
    // deprecated dynamic properties.
    public ?int $relayFetchId = null;
    public ?int $relayId = null;
    public ?string $objectURI = null;
    public ?int $attempts = null;
    public ?string $nextAttemptAt = null;
    public ?string $claimedUntil = null;
    public ?string $createdAt = null;

    /**
     * Notes a post to read later. Silent about a duplicate: two relays naming
     * the same post is one thing to fetch, and the unique key is what settles
     * which of them got there first.
     *
     * A null relay is a post nobody relayed - a reply whose thread this server
     * needs to read before it can place it. It is fetched the same way and
     * simply belongs to no firehose.
     */
    public static function enqueue(string $object_uri, ?int $relay_id): void
    {
        if (self::pendingCount() >= self::MAX_PENDING) {
            return;
        }

        try {
            DB::run('
INSERT INTO `RelayFetches` (`objectURI`, `relayId`)
    VALUES (?, ?)
', 'si', $object_uri, $relay_id);
        } catch (\mysqli_sql_exception $exception) {
            // 1062 = already queued, which is the intended outcome.
            if ($exception -> getCode() !== 1062) {
                throw $exception;
            }
        }
    }

    /**
     * The next posts due to be read, oldest first, claimed for this worker
     * alone - the same expiring-lease claim FediverseDelivery::due() makes,
     * so overlapping workers never read the same post twice.
     *
     * @return self[]
     */
    public static function due(): array
    {
        $limit = self::BATCH_SIZE;
        $lease_seconds = self::CLAIM_SECONDS;

        return DB::transaction(static function () use ($limit, $lease_seconds): array {
            $rows = DB::rows('
SELECT *
    FROM `RelayFetches`
    WHERE `nextAttemptAt` <= NOW() AND (`claimedUntil` IS NULL OR `claimedUntil` <= NOW())
    ORDER BY `nextAttemptAt`, `relayFetchId`
    LIMIT ?
    FOR UPDATE SKIP LOCKED
', self::class, 'i', $limit);

            foreach ($rows as $row) {
                DB::run('
UPDATE `RelayFetches`
    SET `claimedUntil` = NOW() + INTERVAL ? SECOND
    WHERE `relayFetchId` = ?
', 'ii', $lease_seconds, $row -> relayFetchId);
            }

            return $rows;
        });
    }

    public static function done(int $relay_fetch_id): void
    {
        DB::run('
DELETE
    FROM `RelayFetches`
    WHERE `relayFetchId` = ?
', 'i', $relay_fetch_id);
    }

    /**
     * Records a failed read and schedules one retry, or gives up. A short
     * delay rather than the outbound queue's growing backoff: this is a post
     * nobody is waiting for, and holding it for hours to try again buys
     * nothing.
     */
    public static function failed(int $relay_fetch_id, int $attempts): void
    {
        if ($attempts + 1 >= self::MAX_ATTEMPTS) {
            self::done($relay_fetch_id);

            return;
        }

        $delay_seconds = 300;

        // The claim is released with the reschedule, so the retry is
        // anyone's to take when it comes due.
        DB::run('
UPDATE `RelayFetches`
    SET `attempts` = `attempts` + 1, `nextAttemptAt` = NOW() + INTERVAL ? SECOND, `claimedUntil` = NULL
    WHERE `relayFetchId` = ?
', 'ii', $delay_seconds, $relay_fetch_id);
    }

    /** How much is waiting - for the cap above, and the admin services panel. */
    public static function pendingCount(): int
    {
        $row = DB::row('
SELECT COUNT(*) AS `total`
    FROM `RelayFetches`
', 'PostCountData');

        return $row === null ? 0 : (int) $row -> total;
    }
}
