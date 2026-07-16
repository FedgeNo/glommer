<?php

declare(strict_types=1);

class EmailVerification
{
    /**
     * @return bool whether $user ended up verified as a direct result of this
     *   call (i.e. auto-verified because mail delivery itself is broken) -
     *   false when the verification email actually went out (still needs to
     *   be checked) or when this specific address was rejected (still
     *   unverified). Lets a caller like api/signup.php tell a genuinely
     *   "check your inbox" outcome apart from "there's nothing to check,
     *   you're already good to go".
     */
    public static function sendFor(User $user): bool
    {
        $token = self::create((int) $user -> userId);

        $verify_url = ServerURL::absolute('/verify-email?token=' . $token);

        $name = $user -> displayName ?? $user -> username;

        $text_body = 'Hi ' . $name . ',

Please verify your email address by visiting this link:
' . $verify_url . '

This link expires in 24 hours.';

        $html_body = '<p>Hi ' . htmlspecialchars($name) . ',</p>'
            . '<p>Please verify your email address by clicking the link below:</p>'
            . '<p><a href="' . htmlspecialchars($verify_url) . '">Verify Email Address</a></p>'
            . '<p>This link expires in 24 hours.</p>';

        $sent = Mailer::send($user -> email, $name, 'Verify your email address', $text_body, $html_body);

        // Only auto-verify when mail delivery itself is broken/unconfigured -
        // not when this specific address was rejected (Mailer::attempt()
        // reached the destination server fine, and it refused the recipient).
        // Otherwise anyone could bypass verification by signing up with an
        // address engineered to bounce.
        if (!$sent && !Mailer::recipientWasRejected()) {
            // Rather than leaving the user permanently stuck behind the
            // verification gate with no way to ever receive the link that
            // would clear it, verify them directly instead.
            self::markVerified((int) $user -> userId);

            // Let the admin know the mailer is down so they can fix it
            // (throttled, so a flood of failures doesn't pile up). A
            // from-address-not-configured failure already got its own more
            // specific notification straight from Mailer::send() - this is
            // deliberately still sent too (a different, broader signal: mail
            // delivery in general isn't working, whatever the exact cause).
            Notification::warnAdminMailerFailed((int) $user -> userId);

            return true;
        }

        return false;
    }

    public static function verify(string $token): ?int
    {
        $token_hash = hash('sha256', $token);

        $stmt = DB::run('
SELECT `userId`
    FROM `EmailVerifications`
    WHERE `tokenHash` = ? AND `expiresAt` > NOW()
', 's', $token_hash);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);

        if ($row === null) {
            return null;
        }

        $user_id = (int) $row['userId'];

        self::markVerified($user_id);

        DB::run('
DELETE
    FROM `EmailVerifications`
    WHERE `tokenHash` = ?
', 's', $token_hash);

        return $user_id;
    }

    private static function markVerified(int $user_id): void
    {
        $verified = 1;

        DB::run('
UPDATE `Users`
    SET `verified` = ?
    WHERE `userId` = ?
', 'ii', $verified, $user_id);
    }

    private static function create(int $user_id): string
    {
        $token = bin2hex(random_bytes(32));
        $token_hash = hash('sha256', $token);
        $expiry_hours = 24;

        DB::run('
DELETE
    FROM `EmailVerifications`
    WHERE `expiresAt` <= NOW()
');

        DB::run('
INSERT INTO `EmailVerifications` (`userId`, `tokenHash`, `expiresAt`)
    VALUES (?, ?, NOW() + INTERVAL ? HOUR)
', 'isi', $user_id, $token_hash, $expiry_hours);

        return $token;
    }
}
