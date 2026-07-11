<?php

declare(strict_types=1);

/**
 * Persistent "Remember me" login. A token is two parts: a selector (the DB
 * lookup key) and a validator (the secret, stored only as a SHA-256 hash - a
 * database leak alone can't forge a cookie). The pair travels in one cookie as
 * "selector:validator". Tokens are single-use: every successful cookie login
 * deletes the used row and issues a fresh pair, so a copied cookie stops
 * working the next time either copy is used - and a validator mismatch on a
 * known selector (one copy already rotated) revokes every token the user has.
 */
class RememberToken
{
    private const COOKIE_NAME = 'rememberToken';
    private const TTL_DAYS = 30;

    public static function issue(int $user_id): void
    {
        $validator = bin2hex(random_bytes(32));
        $validator_hash = hash('sha256', $validator);
        $ttl_days = self::TTL_DAYS;
        $mysqli = Database::connection();

        // The selector is 96 random bits, so a collision is astronomically
        // unlikely - but it's a one-line retry to not let a freak collision
        // surface as a login failure instead of just trying again.
        $max_attempts = 3;
        $selector = '';

        for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
            $selector = bin2hex(random_bytes(12));

            try {
                $stmt = mysqli_prepare($mysqli, '
INSERT INTO `RememberTokens` (`userId`, `selector`, `validatorHash`, `expiresAt`)
    VALUES (?, ?, ?, NOW() + INTERVAL ? DAY)
');
                mysqli_stmt_bind_param($stmt, 'issi', $user_id, $selector, $validator_hash, $ttl_days);
                mysqli_stmt_execute($stmt);

                break;
            } catch (\mysqli_sql_exception $exception) {
                // 1062 = duplicate key (the selector collided) - anything else
                // is a real problem and should surface normally.
                if ($exception -> getCode() !== 1062 || $attempt === $max_attempts) {
                    throw $exception;
                }
            }
        }

        // Occasionally sweep out expired rows (same lottery approach as
        // RateLimiter) so the table doesn't grow forever.
        if (mt_rand(1, 100) === 1) {
            $prune_stmt = mysqli_prepare(Database::connection(), '
DELETE
    FROM `RememberTokens`
    WHERE `expiresAt` <= NOW()
');
            mysqli_stmt_execute($prune_stmt);
        }

        self::setCookie($selector . ':' . $validator, time() + $ttl_days * 86400);
    }

    /**
     * Re-establishes a session from the remember-me cookie, if a valid one is
     * present. Called from init.php for requests that arrive without a
     * session. The used token is rotated (deleted and reissued) on success.
     */
    public static function loginFromCookie(): void
    {
        $cookie = $_COOKIE[self::COOKIE_NAME] ?? null;

        if (!is_string($cookie) || !str_contains($cookie, ':')) {
            return;
        }

        [$selector, $validator] = explode(':', $cookie, 2);

        $stmt = mysqli_prepare(Database::connection(), '
SELECT `tokenId`, `userId`, `validatorHash`
    FROM `RememberTokens`
    WHERE `selector` = ? AND `expiresAt` > NOW()
');
        mysqli_stmt_bind_param($stmt, 's', $selector);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);

        if ($row === null) {
            self::clearCookie();

            return;
        }

        if (!hash_equals($row['validatorHash'], hash('sha256', $validator))) {
            // The selector matched but the secret didn't - this cookie is a
            // stale copy of a token that was already rotated, which is what a
            // theft looks like (the thief's copy logged in and rotated it, or
            // this is the thief holding the stale copy). Revoke everything
            // rather than guess which side is legitimate.
            self::purgeForUser((int) $row['userId']);
            self::clearCookie();

            return;
        }

        $user = User::load((int) $row['userId']);

        if ($user === null || $user -> banned) {
            self::deleteToken((int) $row['tokenId']);
            self::clearCookie();

            return;
        }

        self::deleteToken((int) $row['tokenId']);
        Auth::login($user);
        self::issue((int) $user -> userId);
    }

    /**
     * Forgets the current browser's token (logout): deletes its DB row and
     * clears the cookie. Other devices' tokens are untouched.
     */
    public static function forget(): void
    {
        $cookie = $_COOKIE[self::COOKIE_NAME] ?? null;

        if (is_string($cookie) && str_contains($cookie, ':')) {
            [$selector] = explode(':', $cookie, 2);

            $stmt = mysqli_prepare(Database::connection(), '
DELETE
    FROM `RememberTokens`
    WHERE `selector` = ?
');
            mysqli_stmt_bind_param($stmt, 's', $selector);
            mysqli_stmt_execute($stmt);
        }

        self::clearCookie();
    }

    /**
     * Revokes every remember-me token the user has, on every device. Used when
     * their password changes (a stolen token mustn't outlive the credentials
     * that created it) and on suspected token theft.
     */
    public static function purgeForUser(int $user_id): void
    {
        $stmt = mysqli_prepare(Database::connection(), '
DELETE
    FROM `RememberTokens`
    WHERE `userId` = ?
');
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
    }

    private static function deleteToken(int $token_id): void
    {
        $stmt = mysqli_prepare(Database::connection(), '
DELETE
    FROM `RememberTokens`
    WHERE `tokenId` = ?
');
        mysqli_stmt_bind_param($stmt, 'i', $token_id);
        mysqli_stmt_execute($stmt);
    }

    private static function setCookie(string $value, int $expires): void
    {
        setcookie(self::COOKIE_NAME, $value, [
            'expires' => $expires,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => ($_SERVER['HTTPS'] ?? '') !== '',
        ]);

        // Keep this request's view of the cookie consistent with what the
        // browser will hold after the response (matters after a rotation).
        $_COOKIE[self::COOKIE_NAME] = $value;
    }

    private static function clearCookie(): void
    {
        setcookie(self::COOKIE_NAME, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => ($_SERVER['HTTPS'] ?? '') !== '',
        ]);

        unset($_COOKIE[self::COOKIE_NAME]);
    }
}
