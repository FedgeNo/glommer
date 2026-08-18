<?php

declare(strict_types=1);

/**
 * A count of something that happened, kept by the day it happened on.
 *
 * The queues cannot answer "how is this going?" - they hold only what has not
 * been dealt with yet, and a delivery that succeeds is deleted the moment it
 * does. So anything worth knowing afterwards is counted here as it happens,
 * and the admin panel reads back a window of days.
 *
 * Counting is meant to be cheap enough to do on the way past: one upserting
 * statement, no read first, no row per event. It is also allowed to fail
 * quietly - a number on a dashboard is never worth failing the work it was
 * counting, so a delivery that went out is not undone because its tally
 * could not be written.
 */
class Statistic
{
    public ?string $name = null;
    public ?string $day = null;
    public int $total = 0;

    /** A federated activity that reached the server it was addressed to. */
    public const DELIVERED = 'delivered';

    /**
     * One this server stopped trying to deliver. Not a single failed attempt -
     * those are retried and are nobody's business - but the end of the road
     * for that activity, which is the number that means something is wrong.
     */
    public const UNDELIVERABLE = 'undeliverable';

    /**
     * How long a day's counts are kept. Long enough for a month-over-month
     * look, short enough that the table stays a rounding error.
     */
    public const KEEP_DAYS = 90;

    /** Adds one to today's tally for something. */
    public static function count(string $name, int $howMany = 1): void
    {
        try {
            DB::run('
INSERT INTO `Statistics` (`name`, `day`, `total`)
    VALUES (?, CURDATE(), ?)
    ON DUPLICATE KEY UPDATE `total` = `total` + VALUES(`total`)
', 'si', $name, $howMany);
        } catch (\mysqli_sql_exception $exception) {
            // Counting is bookkeeping about work already done. Losing a tally
            // is a worse thing to notice than to ignore, and far better than
            // taking down the worker that was doing the work.
            error_log('Could not count ' . $name . ': ' . $exception -> getMessage());
        }
    }

    /** How many of something over the last $days, today included. */
    public static function since(string $name, int $days): int
    {
        $row = DB::row('
SELECT COALESCE(SUM(`total`), 0) AS `total`
    FROM `Statistics`
    WHERE `name` = ? AND `day` >= CURDATE() - INTERVAL ? DAY
', 'PostCountData', 'si', $name, max(0, $days - 1));

        return $row === null ? 0 : (int) $row -> total;
    }

    /** Drops days nobody will look at again. */
    public static function prune(): void
    {
        DB::run('
DELETE
    FROM `Statistics`
    WHERE `day` < CURDATE() - INTERVAL ? DAY
', 'i', self::KEEP_DAYS);
    }
}
