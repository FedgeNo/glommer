<?php

declare(strict_types=1);

class RateLimiterTest extends DatabaseTestCase
{
    public function testALockTimeoutStopsTheAttemptAndAllowsARetryAfterRelease(): void
    {
        $rate_key = 'timeout-test:' . bin2hex(random_bytes(8));
        $lock_name = 'ratelimit:' . md5($rate_key);
        $holder = mysqli_connect(
            (string) Config::get('host'),
            (string) Config::get('username'),
            (string) Config::get('password'),
            (string) Config::get('database'),
            (int) Config::get('port')
        );

        try {
            $stmt = mysqli_prepare($holder, 'SELECT GET_LOCK(?, 0)');
            mysqli_stmt_bind_param($stmt, 's', $lock_name);
            mysqli_stmt_execute($stmt);
            $this -> assertSame(1, (int) mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0]);

            $timed_out = false;

            try {
                if (!RateLimiter::tooManyAttempts($rate_key, 1, 60)) {
                    RateLimiter::recordAttempt($rate_key);
                }
            } catch (RateLimitLockException $exception) {
                $timed_out = true;
            }

            $this -> assertTrue($timed_out, 'a request must fail when another connection keeps the lock');

            $count = DB::row('
SELECT COUNT(*) AS `count`
    FROM `RateLimitAttempts`
    WHERE `rateKey` = ?
', \stdClass::class, 's', $rate_key);
            $this -> assertSame(0, (int) $count -> count, 'the timed-out request must not record an attempt');

            $stmt = mysqli_prepare($holder, 'SELECT RELEASE_LOCK(?)');
            mysqli_stmt_bind_param($stmt, 's', $lock_name);
            mysqli_stmt_execute($stmt);
            $this -> assertSame(1, (int) mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0]);

            $this -> assertFalse(RateLimiter::tooManyAttempts($rate_key, 1, 60), 'a retry can proceed once the lock is available');
            RateLimiter::recordAttempt($rate_key);
            $this -> assertTrue(RateLimiter::tooManyAttempts($rate_key, 1, 60), 'the recorded retry exhausts the limit');
        } finally {
            mysqli_close($holder);
            RateLimiter::releaseLock($rate_key);
            DB::run('DELETE FROM `RateLimitAttempts` WHERE `rateKey` = ?', 's', $rate_key);
        }
    }
}
