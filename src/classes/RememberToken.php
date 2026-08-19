<?php

declare(strict_types=1);

/**
 * Persistent "Remember me" login. A token is two parts: a selector (the DB
 * lookup key) and a validator (the secret, stored only as a SHA-256 hash - a
 * database leak alone can't forge a cookie). The pair travels in one cookie as
 * "selector:validator". Tokens are single-use: every successful cookie login
 * marks the used row consumed and issues a fresh pair in the same transaction.
 * Keeping the consumed selector until its original expiry lets a stale copied
 * cookie be recognised as reuse, at which point every token for the user is
 * revoked rather than guessing which browser holds the legitimate copy.
 */
class RememberToken
{
    public ?int $tokenId = null;
    public ?int $userId = null;
    public ?string $selector = null;
    public ?string $validatorHash = null;
    public ?string $expiresAt = null;
    public ?string $createdAt = null;
    public ?string $lastUsedAt = null;
    public ?string $consumedAt = null;
    public ?string $userAgent = null;
    public ?string $ipAddress = null;

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
        $cookie = self::create($user_id, $carried_created_at);

        self::sweepExpired();
        self::setCookie($cookie, time() + self::TTL_DAYS * 86400);
    }

    /** Creates the database row and returns the selector/validator cookie value. */
    private static function create(int $user_id, ?string $carried_created_at = null): string
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

        return $selector . ':' . $validator;
    }

    /**
     * Re-establishes a session from the remember-me cookie, if a valid one is
     * present. Called from init.php for requests that arrive without a
     * session. The used token is marked consumed and replaced on success.
     */
    public static function loginFromCookie(): void
    {
        $cookie = $_COOKIE[self::COOKIE_NAME] ?? null;

        if (!is_string($cookie) || !str_contains($cookie, ':')) {
            return;
        }

        [$selector, $validator] = explode(':', $cookie, 2);

        $token = DB::row('
SELECT `tokenId`, `userId`, `validatorHash`, `createdAt`, `consumedAt`
    FROM `RememberTokens`
    WHERE `selector` = ? AND `expiresAt` > NOW()
', 'RememberTokenData', 's', $selector);

        if ($token === null) {
            self::clearCookie();

            return;
        }

        if ($token -> consumedAt !== null || !hash_equals((string) $token -> validatorHash, hash('sha256', $validator))) {
            // A consumed selector or a known selector with the wrong secret is
            // a stale copy. Revoke everything rather than guess which browser
            // holds the legitimate copy.
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

        $replacement = self::consumeAndReplace($token);

        if ($replacement === null) {
            // Another request consumed this token after our read. Treat the
            // concurrent reuse exactly like a stale cookie discovered above.
            self::purgeForUser((int) $token -> userId);
            self::clearCookie();

            return;
        }

        Auth::login($user);
        LoginFingerprint::record((int) $user -> userId);
        self::sweepExpired();
        self::setCookie($replacement, time() + self::TTL_DAYS * 86400);
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
    WHERE `selector` = ? AND `consumedAt` IS NULL
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

    /** Atomically spends one token and creates the only valid successor. */
    private static function consumeAndReplace(RememberTokenData $token): ?string
    {
        return DB::transaction(static function () use ($token): ?string {
            $consumed = DB::run('
UPDATE `RememberTokens`
    SET `consumedAt` = NOW(), `lastUsedAt` = NOW()
    WHERE `tokenId` = ? AND `consumedAt` IS NULL
', 'i', $token -> tokenId);

            if (mysqli_stmt_affected_rows($consumed) !== 1) {
                return null;
            }

            return self::create((int) $token -> userId, (string) $token -> createdAt);
        });
    }

    private static function sweepExpired(): void
    {
        // Same lottery approach as RateLimiter keeps tombstones around for
        // theft detection without letting expired rows accumulate forever.
        if (mt_rand(1, 100) !== 1) {
            return;
        }

        DB::run('
DELETE
    FROM `RememberTokens`
    WHERE `expiresAt` <= NOW()
');
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
