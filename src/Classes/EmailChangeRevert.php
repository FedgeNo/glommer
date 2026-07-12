<?php

declare(strict_types=1);

/**
 * The "wasn't you?" safety net for a changed email address: notifies the OLD
 * address (never the new one - the point is reaching whoever actually owned
 * the account before the change) with a link that reverts the email back and
 * signs out every device. No login is required to consume the link - the
 * whole point is recovering an account that may otherwise be unreachable
 * (the new address could be one an attacker controls), so the token itself
 * is the proof of authorization, same as password reset.
 */
class EmailChangeRevert
{
    public static function sendFor(User $user, string $previous_email): void
    {
        $token = self::create((int) $user -> userId, $previous_email);

        $revert_url = URL::absolute('/revert-email?token=' . $token);

        $name = $user -> displayName ?? $user -> username;

        $text_body = 'Hi ' . $name . ',

The email address on your Glommer account was just changed to ' . $user -> email . '.

If this was you, no action is needed.

If this was NOT you, click this link to revert the change and sign every device out of your account:
' . $revert_url . '

This link expires in 30 days.';

        $html_body = '<p>Hi ' . htmlspecialchars($name) . ',</p>'
            . '<p>The email address on your Glommer account was just changed to ' . htmlspecialchars($user -> email) . '.</p>'
            . '<p>If this was you, no action is needed.</p>'
            . '<p><strong>If this was NOT you</strong>, click the link below to revert the change and sign every device out of your account:</p>'
            . '<p><a href="' . htmlspecialchars($revert_url) . '">Revert Email Change</a></p>'
            . '<p>This link expires in 30 days.</p>';

        $sent = Mailer::send($previous_email, $name, 'Your email address was changed', $text_body, $html_body);

        if (!$sent) {
            // The old address is the one place a hijack notice could reach
            // its real owner - if that itself can't be delivered, the admin
            // needs to know the mailer is down (throttled, so a flood of
            // failures doesn't pile up).
            Notification::warnAdminMailerFailed((int) $user -> userId);
        }
    }

    /**
     * Reverts the email change the token was issued for: restores the
     * previous (already-verified) address, signs out every session and
     * remember-me token, and clears any now-moot pending tokens (the revert
     * token itself, any others for the same user, and any pending
     * verification for the abandoned new address). Returns false for an
     * invalid or expired token.
     */
    public static function consume(string $token): bool
    {
        $mysqli = Database::connection();
        $token_hash = hash('sha256', $token);

        $stmt = mysqli_prepare($mysqli, '
SELECT `userId`, `previousEmail`
    FROM `EmailChangeReverts`
    WHERE `tokenHash` = ? AND `expiresAt` > NOW()
');
        mysqli_stmt_bind_param($stmt, 's', $token_hash);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);

        if ($row === null) {
            return false;
        }

        $user_id = (int) $row['userId'];
        $previous_email = $row['previousEmail'];

        // The previous address was already verified before the change
        // happened, so restoring it restores that verified state too - no
        // fresh verification round trip needed.
        $verified = 1;

        $update_stmt = mysqli_prepare($mysqli, '
UPDATE `Users`
    SET `email` = ?, `verified` = ?
    WHERE `userId` = ?
');
        mysqli_stmt_bind_param($update_stmt, 'sii', $previous_email, $verified, $user_id);
        mysqli_stmt_execute($update_stmt);

        // Every session and remember-me token dies with the change - the
        // change that triggered this was either unauthorized or at minimum
        // suspicious enough to warrant it.
        User::bumpSessionVersion($user_id);
        RememberToken::purgeForUser($user_id);

        // Any pending verification for the abandoned new address is moot -
        // and any other pending revert token for this user (e.g. from a rapid
        // second change) is moot too, since this one already reverted things.
        $prune_verifications_stmt = mysqli_prepare($mysqli, '
DELETE
    FROM `EmailVerifications`
    WHERE `userId` = ?
');
        mysqli_stmt_bind_param($prune_verifications_stmt, 'i', $user_id);
        mysqli_stmt_execute($prune_verifications_stmt);

        $prune_reverts_stmt = mysqli_prepare($mysqli, '
DELETE
    FROM `EmailChangeReverts`
    WHERE `userId` = ?
');
        mysqli_stmt_bind_param($prune_reverts_stmt, 'i', $user_id);
        mysqli_stmt_execute($prune_reverts_stmt);

        return true;
    }

    private static function create(int $user_id, string $previous_email): string
    {
        $mysqli = Database::connection();
        $token = bin2hex(random_bytes(32));
        $token_hash = hash('sha256', $token);
        // Unlike a password reset or email verification link (both expected
        // to be used within minutes), this one is a safety net someone might
        // not see for a while - if their inbox itself was part of what got
        // compromised, or they just don't check that address often. A long
        // window matters more here than the usual short-lived-token hygiene.
        $expiry_days = 30;

        $prune_stmt = mysqli_prepare($mysqli, '
DELETE
    FROM `EmailChangeReverts`
    WHERE `expiresAt` <= NOW()
');
        mysqli_stmt_execute($prune_stmt);

        $stmt = mysqli_prepare($mysqli, '
INSERT INTO `EmailChangeReverts` (`userId`, `previousEmail`, `tokenHash`, `expiresAt`)
    VALUES (?, ?, ?, NOW() + INTERVAL ? DAY)
');
        mysqli_stmt_bind_param($stmt, 'issi', $user_id, $previous_email, $token_hash, $expiry_days);
        mysqli_stmt_execute($stmt);

        return $token;
    }
}
