<?php

declare(strict_types=1);

class RateLimiter
{
    public static function tooManyAttempts(string $rate_key, int $max_attempts, int $window_seconds): bool
    {
        $stmt = mysqli_prepare(Database::connection(), '
SELECT COUNT(*) AS `count`
    FROM `RateLimitAttempts`
    WHERE `rateKey` = ? AND `createdAt` > NOW() - INTERVAL ? SECOND
');
        mysqli_stmt_bind_param($stmt, 'si', $rate_key, $window_seconds);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $count = (int) mysqli_fetch_assoc($result)['count'];

        return $count >= $max_attempts;
    }

    public static function recordAttempt(string $rate_key): void
    {
        $mysqli = Database::connection();

        $stmt = mysqli_prepare($mysqli, '
INSERT INTO `RateLimitAttempts` (`rateKey`)
    VALUES (?)
');
        mysqli_stmt_bind_param($stmt, 's', $rate_key);
        mysqli_stmt_execute($stmt);

        // Occasionally sweep out stale attempts (same lottery approach as PHP's
        // session GC) so the table doesn't grow forever. One day comfortably
        // exceeds every window currently in use.
        if (mt_rand(1, 100) === 1) {
            $day_seconds = 86400;

            $prune_stmt = mysqli_prepare($mysqli, '
DELETE
    FROM `RateLimitAttempts`
    WHERE `createdAt` < NOW() - INTERVAL ? SECOND
');
            mysqli_stmt_bind_param($prune_stmt, 'i', $day_seconds);
            mysqli_stmt_execute($prune_stmt);
        }
    }
}
