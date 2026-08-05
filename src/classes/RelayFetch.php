<?php

declare(strict_types=1);

/**
 * The queue of posts a relay has named and this server has yet to read.
 *
 * A relay forwards a post's address rather than the post, so taking one means
 * fetching it from the server that wrote it - and that cannot happen while the
 * relay waits. Two signed fetches at five seconds each, redirects allowed, is
 * tens of seconds of a PHP worker held on somebody else's server; at the rate
 * the inbox already permits, that is enough concurrent workers to exhaust the
 * pool and stop the site answering at all. So the inbox writes down what to
 * read and returns, and bin/federation-worker.php does the reading.
 *
 * Unlike an outbound delivery, what is queued here is best-effort. Nobody
 * asked for any of it, so the queue is bounded and sheds new arrivals rather
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

    // Declared so a row fetched via DB::row()/DB::rows() doesn't set them as
    // deprecated dynamic properties.
    public ?int $relayFetchId = null;
    public ?int $relayId = null;
    public ?string $objectURI = null;
    public ?int $attempts = null;
    public ?string $nextAttemptAt = null;
    public ?string $createdAt = null;

    /**
     * Notes a post to read later. Silent about a duplicate: two relays naming
     * the same post is one thing to fetch, and the unique key is what settles
     * which of them got there first.
     */
    public static function enqueue(string $object_uri, int $relay_id): void
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
     * The next posts due to be read, oldest first.
     *
     * @return self[]
     */
    public static function due(): array
    {
        $limit = self::BATCH_SIZE;

        return DB::rows('
SELECT *
    FROM `RelayFetches`
    WHERE `nextAttemptAt` <= NOW()
    ORDER BY `nextAttemptAt`, `relayFetchId`
    LIMIT ?
', self::class, 'i', $limit);
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

        DB::run('
UPDATE `RelayFetches`
    SET `attempts` = `attempts` + 1, `nextAttemptAt` = NOW() + INTERVAL ? SECOND
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
