<?php

declare(strict_types=1);

class EmailVerification
{
    public static function sendFor(User $user): void
    {
        $token = self::create((int) $user -> userId);

        $verify_url = URL::absolute('/verify-email/?token=' . $token);

        $name = $user -> displayName ?? $user -> username;

        $text_body = 'Hi ' . $name . ',

Please verify your email address by visiting this link:
' . $verify_url . '

This link expires in 24 hours.';

        $html_body = '<p>Hi ' . htmlspecialchars($name) . ',</p>'
            . '<p>Please verify your email address by clicking the link below:</p>'
            . '<p><a href="' . htmlspecialchars($verify_url) . '">Verify Email Address</a></p>'
            . '<p>This link expires in 24 hours.</p>';

        Mailer::send($user -> email, $name, 'Verify your email address', $text_body, $html_body);
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
        $verified = 1;

        $update_stmt = mysqli_prepare($mysqli, '
UPDATE `Users`
    SET `verified` = ?
    WHERE `userId` = ?
');
        mysqli_stmt_bind_param($update_stmt, 'ii', $verified, $user_id);
        mysqli_stmt_execute($update_stmt);

        $delete_stmt = mysqli_prepare($mysqli, '
DELETE
    FROM `EmailVerifications`
    WHERE `tokenHash` = ?
');
        mysqli_stmt_bind_param($delete_stmt, 's', $token_hash);
        mysqli_stmt_execute($delete_stmt);

        return $user_id;
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
