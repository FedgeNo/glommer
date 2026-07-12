<?php

declare(strict_types=1);

class Auth
{
    private static ?User $cachedUser = null;
    private static bool $userCacheFilled = false;

    public static function attempt(string $identifier, string $password): ?User
    {
        $mysqli = Database::connection();

        $stmt = mysqli_prepare($mysqli, '
SELECT *
    FROM `Users`
    WHERE `username` = ? OR `email` = ?
');
        mysqli_stmt_bind_param($stmt, 'ss', $identifier, $identifier);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_object($result, User::class);

        if ($user === null || $user -> passwordHash === null || !password_verify($password, $user -> passwordHash)) {
            return null;
        }

        if ($user -> banned) {
            return null;
        }

        self::login($user);

        return $user;
    }

    public static function login(User $user): void
    {
        session_regenerate_id(true);
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
