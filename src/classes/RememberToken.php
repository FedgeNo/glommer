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

    /**
     * $carried_created_at is only passed by loginFromCookie()'s rotation - it
     * carries the ORIGINAL token's createdAt forward to the replacement row,
     * so a device's "first seen" date on the sessions list stays stable
     * across every auto-login rotation instead of resetting to "now" every
     * time. A fresh login (password form, OAuth, signup) leaves it null and
     * gets NOW(), same as before this existed.
     */
    public static function issue(int $user_id, ?string $carried_created_at = null): void
    {
        $validator = bin2hex(random_bytes(32));
        $validator_hash = hash('sha256', $validator);
        $ttl_days = self::TTL_DAYS;
        $user_agent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null;
        $ip_address = ServerURL::clientIP();
        $created_at = $carried_created_at ?? date('Y-m-d H:i:s');

        // The selector is 96 random bits, so a collision is astronomically
        // unlikely - but it's a one-line retry to not let a freak collision
        // surface as a login failure instead of just trying again.
        $max_attempts = 3;
        $selector = '';

        for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
            $selector = bin2hex(random_bytes(12));

            try {
                DB::run('
INSERT INTO `RememberTokens` (`userId`, `selector`, `validatorHash`, `expiresAt`, `createdAt`, `lastUsedAt`, `userAgent`, `ipAddress`)
    VALUES (?, ?, ?, NOW() + INTERVAL ? DAY, ?, NOW(), ?, ?)
', 'ississs', $user_id, $selector, $validator_hash, $ttl_days, $created_at, $user_agent, $ip_address);

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
            DB::run('
DELETE
    FROM `RememberTokens`
    WHERE `expiresAt` <= NOW()
');
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

        $token = DB::row('
SELECT `tokenId`, `userId`, `validatorHash`, `createdAt`
    FROM `RememberTokens`
    WHERE `selector` = ? AND `expiresAt` > NOW()
', 'RememberTokenData', 's', $selector);

        if ($token === null) {
            self::clearCookie();

            return;
        }

        if (!hash_equals((string) $token -> validatorHash, hash('sha256', $validator))) {
            // The selector matched but the secret didn't - this cookie is a
            // stale copy of a token that was already rotated, which is what a
            // theft looks like (the thief's copy logged in and rotated it, or
            // this is the thief holding the stale copy). Revoke everything
            // rather than guess which side is legitimate.
            self::purgeForUser((int) $token -> userId);
            self::clearCookie();

            return;
        }

        $user = User::load((int) $token -> userId);

        if ($user === null || $user -> banned) {
            self::deleteToken((int) $token -> tokenId);
            self::clearCookie();

            return;
        }

        self::deleteToken((int) $token -> tokenId);
        Auth::login($user);
        self::issue((int) $user -> userId, (string) $token -> createdAt);
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

            DB::run('
DELETE
    FROM `RememberTokens`
    WHERE `selector` = ?
', 's', $selector);
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
        DB::run('
DELETE
    FROM `RememberTokens`
    WHERE `userId` = ?
', 'i', $user_id);
    }

    /**
     * A user's own remembered devices for the Settings page - every
     * non-expired token, newest-used first. Never exposes the validator (or
     * its hash); the selector is only used to match against the current
     * browser's cookie, not shown to the user.
     *
     * @return RememberTokenData[]
     */
    public static function rowsForUser(int $user_id): array
    {
        return DB::rows('
SELECT `tokenId`, `selector`, `createdAt`, `lastUsedAt`, `userAgent`, `ipAddress`
    FROM `RememberTokens`
    WHERE `userId` = ? AND `expiresAt` > NOW()
    ORDER BY `lastUsedAt` DESC
', 'RememberTokenData', 'i', $user_id);
    }

    /**
     * The current browser's token selector, if its cookie is present - used
     * to mark "this device" in the Settings list. Never the validator.
     */
    public static function currentSelector(): ?string
    {
        $cookie = $_COOKIE[self::COOKIE_NAME] ?? null;

        if (!is_string($cookie) || !str_contains($cookie, ':')) {
            return null;
        }

        [$selector] = explode(':', $cookie, 2);

        return $selector;
    }

    /**
     * Revokes one specific device, scoped to $user_id so a user can only ever
     * revoke their own tokens (not just any tokenId they guess). Returns
     * whether a row actually matched and was deleted.
     */
    public static function revoke(int $token_id, int $user_id): bool
    {
        $stmt = DB::run('
DELETE
    FROM `RememberTokens`
    WHERE `tokenId` = ? AND `userId` = ?
', 'ii', $token_id, $user_id);

        return mysqli_stmt_affected_rows($stmt) > 0;
    }

    private static function deleteToken(int $token_id): void
    {
        DB::run('
DELETE
    FROM `RememberTokens`
    WHERE `tokenId` = ?
', 'i', $token_id);
    }

    private static function setCookie(string $value, int $expires): void
    {
        setcookie(self::COOKIE_NAME, $value, [
            'expires' => $expires,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => ServerURL::isHTTPS(),
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
            'secure' => ServerURL::isHTTPS(),
        ]);

        unset($_COOKIE[self::COOKIE_NAME]);
    }
}
