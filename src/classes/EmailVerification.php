<?php

declare(strict_types=1);

class EmailVerification
{
    public static function sendFor(User $user): void
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
            // (throttled, so a flood of failures doesn't pile up).
            Notification::warnAdminMailerFailed((int) $user -> userId);
        }
    }

    public static function verify(string $token): ?int
    {
        $mysqli = Database::connection();
        $token_hash = hash('sha256', $token);

        $stmt = mysqli_prepare($mysqli, '
SELECT `userId`
    FROM `EmailVerifications`
    WHERE `tokenHash` = ? AND `expiresAt` > NOW()
');
        mysqli_stmt_bind_param($stmt, 's', $token_hash);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);

        if ($row === null) {
            return null;
        }

        $user_id = (int) $row['userId'];

        self::markVerified($user_id);

        $delete_stmt = mysqli_prepare($mysqli, '
DELETE
    FROM `EmailVerifications`
    WHERE `tokenHash` = ?
');
        mysqli_stmt_bind_param($delete_stmt, 's', $token_hash);
        mysqli_stmt_execute($delete_stmt);

        return $user_id;
    }

    private static function markVerified(int $user_id): void
    {
        $verified = 1;

        $stmt = mysqli_prepare(Database::connection(), '
UPDATE `Users`
    SET `verified` = ?
    WHERE `userId` = ?
');
        mysqli_stmt_bind_param($stmt, 'ii', $verified, $user_id);
        mysqli_stmt_execute($stmt);
    }

    private static function create(int $user_id): string
    {
        $mysqli = Database::connection();
        $token = bin2hex(random_bytes(32));
        $token_hash = hash('sha256', $token);
        $expiry_hours = 24;

        $prune_stmt = mysqli_prepare($mysqli, '
DELETE
    FROM `EmailVerifications`
    WHERE `expiresAt` <= NOW()
');
        mysqli_stmt_execute($prune_stmt);

        $stmt = mysqli_prepare($mysqli, '
INSERT INTO `EmailVerifications` (`userId`, `tokenHash`, `expiresAt`)
    VALUES (?, ?, NOW() + INTERVAL ? HOUR)
');
        mysqli_stmt_bind_param($stmt, 'isi', $user_id, $token_hash, $expiry_hours);
        mysqli_stmt_execute($stmt);

        return $token;
    }
}
