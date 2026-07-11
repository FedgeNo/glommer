<?php

declare(strict_types=1);

class PasswordReset
{
    public static function sendFor(User $user): void
    {
        $token = self::create((int) $user -> userId);

        $reset_url = URL::absolute('/reset-password/?token=' . $token);

        $name = $user -> displayName ?? $user -> username;

        $text_body = 'Hi ' . $name . ',

Someone (hopefully you) requested a password reset. Visit this link to choose a new password:
' . $reset_url . '

This link expires in 1 hour. If you did not request this, you can ignore this email.';

        $html_body = '<p>Hi ' . htmlspecialchars($name) . ',</p>'
            . '<p>Someone (hopefully you) requested a password reset. Click the link below to choose a new password:</p>'
            . '<p><a href="' . htmlspecialchars($reset_url) . '">Reset Password</a></p>'
            . '<p>This link expires in 1 hour. If you did not request this, you can ignore this email.</p>';

        $sent = Mailer::send($user -> email, $name, 'Reset your password', $text_body, $html_body);

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

        $stmt = mysqli_prepare(Database::connection(), '
SELECT `userId`
    FROM `PasswordResets`
    WHERE `tokenHash` = ? AND `expiresAt` > NOW()
');
        mysqli_stmt_bind_param($stmt, 's', $token_hash);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);

        return $row !== null ? (int) $row['userId'] : null;
    }

    public static function consume(string $token, string $new_password): bool
    {
        $user_id = self::verify($token);

        if ($user_id === null) {
            return false;
        }

        $mysqli = Database::connection();
        $hash = password_hash($new_password, PASSWORD_DEFAULT);

        $update_stmt = mysqli_prepare($mysqli, '
UPDATE `Users`
    SET `passwordHash` = ?
    WHERE `userId` = ?
');
        mysqli_stmt_bind_param($update_stmt, 'si', $hash, $user_id);
        mysqli_stmt_execute($update_stmt);

        // The old password's sessions and remember-me tokens die with it -
        // whoever prompted the reset may not be the only one logged in.
        User::bumpSessionVersion($user_id);
        RememberToken::purgeForUser($user_id);

        $token_hash = hash('sha256', $token);
        $delete_stmt = mysqli_prepare($mysqli, '
DELETE
    FROM `PasswordResets`
    WHERE `tokenHash` = ?
');
        mysqli_stmt_bind_param($delete_stmt, 's', $token_hash);
        mysqli_stmt_execute($delete_stmt);

        return true;
    }

    private static function create(int $user_id): string
    {
        $mysqli = Database::connection();
        $token = bin2hex(random_bytes(32));
        $token_hash = hash('sha256', $token);
        $expiry_hours = 1;

        $prune_stmt = mysqli_prepare($mysqli, '
DELETE
    FROM `PasswordResets`
    WHERE `expiresAt` <= NOW()
');
        mysqli_stmt_execute($prune_stmt);

        $stmt = mysqli_prepare($mysqli, '
INSERT INTO `PasswordResets` (`userId`, `tokenHash`, `expiresAt`)
    VALUES (?, ?, NOW() + INTERVAL ? HOUR)
');
        mysqli_stmt_bind_param($stmt, 'isi', $user_id, $token_hash, $expiry_hours);
        mysqli_stmt_execute($stmt);

        return $token;
    }
}
