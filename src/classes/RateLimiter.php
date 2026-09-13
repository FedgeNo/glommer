<?php

declare(strict_types=1);

class RateLimiter
{
    public static function tooManyAttempts(string $rate_key, int $max_attempts, int $window_seconds, int $lock_timeout_seconds = 5): bool
    {
        // Serialize the check-then-record window per key. Without this, two
        // concurrent requests can both read a count below the limit before
        // either has recorded its attempt, so both slip through. The lock is
        // held across into recordAttempt() (which releases it) so the count and
        // the insert that follows it are one atomic step. On the blocked path
        // below - where no record follows - it's released before returning.
        self::acquireLock($rate_key, $lock_timeout_seconds);

        $stmt = DB::run('
SELECT COUNT(*) AS `count`
    FROM `RateLimitAttempts`
    WHERE `rateKey` = ? AND `createdAt` > NOW() - INTERVAL ? SECOND
', 'si', $rate_key, $window_seconds);
        $result = mysqli_stmt_get_result($stmt);
        $count = (int) mysqli_fetch_assoc($result)['count'];

        if ($count >= $max_attempts) {
            self::releaseLock($rate_key);

            return true;
        }

        return false;
    }

    public static function recordAttempt(string $rate_key): void
    {
        DB::run('
INSERT INTO `RateLimitAttempts` (`rateKey`)
    VALUES (?)
', 's', $rate_key);

        // Occasionally sweep out stale attempts (same lottery approach as PHP's
        // session GC) so the table doesn't grow forever. One day comfortably
        // exceeds every window currently in use.
        if (mt_rand(1, 100) === 1) {
            $day_seconds = 86400;

            DB::run('
DELETE
    FROM `RateLimitAttempts`
    WHERE `createdAt` < NOW() - INTERVAL ? SECOND
', 'i', $day_seconds);
        }

        // Release the per-key lock taken in tooManyAttempts() now that the
        // matching attempt is recorded. A caller that checked but never records
        // (e.g. a successful login) simply leaves it to be freed when its
        // request's connection closes - one process per request, so no leak.
        self::releaseLock($rate_key);
    }

    /**
     * Public so a caller with its own check-then-act sequence to protect
     * (not a plain tooManyAttempts()/recordAttempt() pair) can take the same
     * named lock directly - see Message::unansweredCount()'s use in
     * api/send-message.php, which needs the check and the insert atomic but
     * has nothing to "record" separately.
     */
    public static function acquireLock(string $rate_key, int $timeout_seconds = 5): void
    {
        $lock_name = self::lockName($rate_key);

        // A timeout must stop the caller before its protected check or write.
        // The current holder may still be processing an unrecorded attempt.
        $stmt = DB::run('
SELECT GET_LOCK(?, ?)
', 'si', $lock_name, $timeout_seconds);
        $acquired = mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0];

        if ((int) $acquired !== 1) {
            throw new RateLimitLockException('Could not acquire a rate-limit lock within the deadline.');
        }
    }

    public static function releaseLock(string $rate_key): void
    {
        $lock_name = self::lockName($rate_key);

        // RELEASE_LOCK on a lock this connection doesn't hold is a harmless
        // no-op, so cleanup after an already released lock is safe.
        $stmt = DB::run('
SELECT RELEASE_LOCK(?)
', 's', $lock_name);
        mysqli_stmt_get_result($stmt);
    }

    private static function lockName(string $rate_key): string
    {
        // GET_LOCK names are capped at 64 characters and share one server-wide
        // namespace, so hash the (arbitrary-length) rate key into a fixed,
        // prefixed name that always fits and won't collide with other lock users.
        return 'ratelimit:' . md5($rate_key);
    }
}
