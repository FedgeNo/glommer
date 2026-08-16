<?php

declare(strict_types=1);

class PasswordReset
{
    /** How long the link is good for, said in the mail and enforced in the DB row alike. */
    private const EXPIRY_HOURS = 1;

    public static function sendFor(User $user): void
    {
        $token = self::create((int) $user -> userId);

        $reset_url = ServerURL::absolute('/reset-password?token=' . $token);

        $name = $user -> title ?: $user -> slug;

        // Read in the account's own language, not whoever is sitting at this
        // browser - a reset is requested while signed out, so the visitor and
        // the account it names are not reliably the same person.
        Strings::useLocale($user -> locale ?? Strings::SOURCE_LOCALE);

        try {
            $words = Strings::for(self::class);
            $subject = (string) ($words['subject'] ?? '');
            $expiry = str_replace('{count}', (string) self::EXPIRY_HOURS, Strings::plural(self::class, 'expiresIn', self::EXPIRY_HOURS))
                . ' ' . (string) ($words['ignoreNotice'] ?? '');

            $text_body = str_replace('{name}', $name, (string) ($words['greeting'] ?? '')) . chr(10) . chr(10)
                . (string) ($words['instructions'] ?? '') . chr(10)
                . $reset_url . chr(10) . chr(10)
                . $expiry;

            $html_body = '<p>' . str_replace('{name}', htmlspecialchars($name), (string) ($words['greeting'] ?? '')) . '</p>'
                . '<p>' . htmlspecialchars((string) ($words['htmlInstructions'] ?? '')) . '</p>'
                . '<p><a href="' . htmlspecialchars($reset_url) . '">' . htmlspecialchars((string) ($words['buttonLabel'] ?? '')) . '</a></p>'
                . '<p>' . htmlspecialchars($expiry) . '</p>';
        } finally {
            Strings::useLocale(null);
        }

        $sent = Mailer::send($user -> email, $name, $subject, $text_body, $html_body);

        if (!$sent) {
            // Unlike sign-up, a failed reset can't just proceed - without the
            // emailed link there's nothing to let the user through with. Still
            // tell the admin the mailer is down so they can fix it (throttled,
            // so a flood of failures doesn't pile up).
            Notification::warnAdminMailerFailed((int) $user -> userId);
        }
    }

    public static function verify(string $token): ?int
    {
        $token_hash = hash('sha256', $token);

        $reset = DB::row('
SELECT `userId`
    FROM `PasswordResets`
    WHERE `tokenHash` = ? AND `expiresAt` > NOW()
', 'PasswordResetData', 's', $token_hash);

        return $reset !== null ? (int) $reset -> userId : null;
    }

    public static function consume(string $token, string $new_password): bool
    {
        $user_id = self::claim($token);

        if ($user_id === null) {
            return false;
        }

        $hash = password_hash($new_password, PASSWORD_DEFAULT);

        DB::run('
UPDATE `Users`
    SET `passwordHash` = ?
    WHERE `userId` = ?
', 'si', $hash, $user_id);

        // The old password's sessions and remember-me tokens die with it -
        // whoever prompted the reset may not be the only one logged in.
        User::bumpSessionVersion($user_id);
        RememberToken::purgeForUser($user_id);

        return true;
    }

    /**
     * Spends the token and says whose it was, or null where it was already
     * spent or has expired.
     *
     * The delete is the check. Reading the row and deleting it afterwards
     * leaves a window where two requests holding the same token both read it
     * as good and both set a password - and the one that lands second is the
     * one that stands, so somebody who got hold of the link could race the
     * person it was sent to and end up owning the account they were both
     * trying to rescue. A DELETE reports how many rows it took, and only one
     * request can be told it took this one.
     */
    private static function claim(string $token): ?int
    {
        $token_hash = hash('sha256', $token);

        $reset = DB::row('
SELECT `userId`
    FROM `PasswordResets`
    WHERE `tokenHash` = ? AND `expiresAt` > NOW()
', 'PasswordResetData', 's', $token_hash);

        if ($reset === null) {
            return null;
        }

        // Read off the statement itself, not the connection: a discarded
        // \mysqli_stmt is closed the moment nothing references it, and that
        // close sends its own command over the connection - which clobbers
        // mysqli_affected_rows(DB::connection()) before this line would ever
        // get to read it, back to its "no info" value of -1. The statement's
        // own count was captured at execution and outlives its own closing.
        $deleted = DB::run('
DELETE
    FROM `PasswordResets`
    WHERE `tokenHash` = ?
', 's', $token_hash);

        return mysqli_stmt_affected_rows($deleted) === 1 ? (int) $reset -> userId : null;
    }

    private static function create(int $user_id): string
    {
        $token = bin2hex(random_bytes(32));
        $token_hash = hash('sha256', $token);

        DB::run('
DELETE
    FROM `PasswordResets`
    WHERE `expiresAt` <= NOW()
');

        DB::run('
INSERT INTO `PasswordResets` (`userId`, `tokenHash`, `expiresAt`)
    VALUES (?, ?, NOW() + INTERVAL ? HOUR)
', 'isi', $user_id, $token_hash, self::EXPIRY_HOURS);

        return $token;
    }
}
