<?php

declare(strict_types=1);

class Auth
{
    private static ?User $cachedUser = null;
    private static bool $userCacheFilled = false;

    public static function attempt(string $identifier, string $password): ?User
    {
        $user = self::verifyCredentials($identifier, $password);

        if ($user === null || $user -> banned) {
            return null;
        }

        self::login($user);

        return $user;
    }

    /**
     * Returns the user whose credentials match (banned or not), or null if the
     * identifier/password is wrong. Unlike attempt() this neither gates on the
     * ban nor logs anyone in - login.php uses it to tell a banned sign-in
     * (correct password, show the ban reason) apart from a wrong one.
     */
    public static function verifyCredentials(string $identifier, string $password): ?User
    {
        $user = DB::row('
SELECT *
    FROM `Users`
    WHERE `slug` = ? OR `email` = ?
', 'User', 'ss', $identifier, $identifier);

        if ($user === null || $user -> passwordHash === null || !password_verify($password, $user -> passwordHash)) {
            return null;
        }

        // Now that we (briefly) hold the plaintext and know it's correct,
        // transparently upgrade a hash made with an older algorithm/cost to the
        // current PASSWORD_DEFAULT. A no-op today (still bcrypt); the standard
        // migration hook for whenever PHP's default moves on or the cost is
        // raised. Covers both callers - login and the banned-vs-wrong check.
        self::rehashIfNeeded($user, $password);

        return $user;
    }

    /**
     * Re-hashes a user's password to the current PASSWORD_DEFAULT and stores it
     * when their existing hash was made with a different algorithm or cost.
     * Called only on a successful credential check (the sole moment the
     * plaintext is available). Best-effort: a rehash/update failure is swallowed
     * so it can never block an otherwise-valid sign-in - the old hash still
     * verifies, and the next login just tries again.
     */
    private static function rehashIfNeeded(User $user, string $password): void
    {
        if (!password_needs_rehash($user -> passwordHash, PASSWORD_DEFAULT)) {
            return;
        }

        $new_hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            DB::run('
UPDATE `Users`
    SET `passwordHash` = ?
    WHERE `userId` = ?
', 'si', $new_hash, $user -> userId);
            $user -> passwordHash = $new_hash;
        } catch (\mysqli_sql_exception $exception) {
            // Old hash still verifies; leave it and retry on the next sign-in.
        }
    }

    public static function login(User $user): void
    {
        session_regenerate_id(true);

        // Any half-finished 2FA login is now moot - drop its pending state so
        // it can't outlive an unrelated completed login in the same session
        // (session data survives session_regenerate_id, so an abandoned
        // password-step login left behind pending2FAUserId that a later,
        // different login here would otherwise inherit).
        unset($_SESSION['pending2FAUserId'], $_SESSION['pending2FARememberMe']);

        $_SESSION['userId'] = $user -> userId;

        // Record the sessionVersion this session was created under - a
        // password change bumps the user's version, and init.php logs out any
        // session whose recorded version no longer matches.
        $_SESSION['sessionVersion'] = $user -> sessionVersion;

        self::clearUserCache();

        // Keep the friend-cap cache honest on every sign-in, in case a
        // friendship changed through a path that didn't adjust it.
        User::recomputeFriendCount((int) $user -> userId);
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
        self::clearUserCache();
    }

    public static function check(): bool
    {
        return isset($_SESSION['userId']);
    }

    public static function id(): ?int
    {
        return $_SESSION['userId'] ?? null;
    }

    public static function user(): ?User
    {
        if (!self::check()) {
            return null;
        }

        if (!self::$userCacheFilled) {
            self::$cachedUser = User::load((int) self::id());
            self::$userCacheFilled = true;
        }

        return self::$cachedUser;
    }

    public static function clearUserCache(): void
    {
        self::$cachedUser = null;
        self::$userCacheFilled = false;
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: ' . ServerURL::absolute('/login'));
            exit;
        }
    }

    /**
     * True for the primary admin (userId 1) or anyone promoted to moderator -
     * both can access the reports queue and ban users. Only the primary
     * admin can promote/demote moderators themselves (checked separately,
     * directly against id() === 1, same as every other admin-only action).
     */
    public static function canModerate(): bool
    {
        if (self::id() === 1) {
            return true;
        }

        return (bool) (self::user() ?-> isMod ?? false);
    }
}
