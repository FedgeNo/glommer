<?php

declare(strict_types=1);

/**
 * Opt-in, email-only two-factor authentication. When a user turns it on, a
 * password login no longer completes on the password alone: a short-lived
 * numeric code is emailed to their (already verified) address, and login
 * only finishes once that code is entered (api/login.php ->
 * api/verify-2fa.php). Codes are stored only as a SHA-256 hash, one active
 * code per user (the UNIQUE key on userId replaces any prior one), expire
 * quickly, and are attempt-capped so the short numeric space can't be
 * brute-forced within a code's lifetime.
 *
 * Deliberately email-only (no TOTP/SMS): it reuses the verified-email
 * channel the site already has, needs no authenticator-app enrollment, and
 * degrades safely - if the site's own mailer is down, api/login.php falls
 * back to a normal login rather than locking every 2FA user out (same
 * philosophy EmailVerification already applies).
 */
class TwoFactor
{
    private const CODE_TTL_MINUTES = 10;
    private const MAX_ATTEMPTS = 5;

    public static function isEnabled(User $user): bool
    {
        return (bool) ($user -> twoFactorEnabled ?? false);
    }

    public static function setEnabled(int $user_id, bool $enabled): void
    {
        $flag = $enabled ? 1 : 0;

        DB::run('
UPDATE `Users`
    SET `twoFactorEnabled` = ?
    WHERE `userId` = ?
', 'ii', $flag, $user_id);

        // Turning it off leaves no reason to keep a pending code around.
        if (!$enabled) {
            self::clear($user_id);
        }
    }

    /**
     * Generates a fresh code, stores its hash (replacing any prior pending
     * code for this user), and emails it. Returns whether the email actually
     * went out - the caller (api/login.php) uses false to fall back to a
     * normal login rather than locking the user out when the mailer itself
     * is broken.
     */
    public static function sendCode(User $user): bool
    {
        $code = self::generateCode();
        $code_hash = hash('sha256', $code);
        $ttl_minutes = self::CODE_TTL_MINUTES;
        $user_id = (int) $user -> userId;
        $initial_attempts = 0;
        $reset_attempts = 0;

        // One active code per user: ON DUPLICATE KEY UPDATE overwrites the
        // previous code, resets its attempt counter, and restarts the clock.
        DB::run('
INSERT INTO `TwoFactorCodes` (`userId`, `codeHash`, `expiresAt`, `attempts`)
    VALUES (?, ?, NOW() + INTERVAL ? MINUTE, ?)
    ON DUPLICATE KEY UPDATE `codeHash` = VALUES(`codeHash`), `expiresAt` = VALUES(`expiresAt`), `attempts` = ?, `createdAt` = NOW()
', 'isiii', $user_id, $code_hash, $ttl_minutes, $initial_attempts, $reset_attempts);

        $name = $user -> displayName ?? $user -> username;

        $text_body = 'Hi ' . $name . ',

Your login verification code is: ' . $code . '

It expires in ' . self::CODE_TTL_MINUTES . ' minutes. If you didn\'t just try to log in, someone may have your password - change it as soon as you can.';

        $html_body = '<p>Hi ' . htmlspecialchars($name) . ',</p>'
            . '<p>Your login verification code is:</p>'
            . '<p style="font-size: 1.5em; font-weight: bold; letter-spacing: 0.2em;">' . htmlspecialchars($code) . '</p>'
            . '<p>It expires in ' . self::CODE_TTL_MINUTES . ' minutes. If you didn\'t just try to log in, someone may have your password - change it as soon as you can.</p>';

        return Mailer::send($user -> email, $name, 'Your login verification code', $text_body, $html_body);
    }

    /**
     * Checks a submitted code for the user. Returns true only on an exact,
     * unexpired, under-the-attempt-cap match - and consumes the code (deletes
     * the row) on success so it can't be replayed. A wrong guess increments
     * the attempt counter; once it hits MAX_ATTEMPTS the code is burned
     * (deleted) so the whole login must restart with a freshly emailed code.
     */
    public static function verifyCode(int $user_id, string $code): bool
    {
        $stored_code = DB::row('
SELECT `codeId`, `codeHash`, `attempts`
    FROM `TwoFactorCodes`
    WHERE `userId` = ? AND `expiresAt` > NOW()
', 'TwoFactorCodeData', 'i', $user_id);

        if ($stored_code === null) {
            return false;
        }

        if ($stored_code -> attempts >= self::MAX_ATTEMPTS) {
            self::clear($user_id);

            return false;
        }

        if (!hash_equals((string) $stored_code -> codeHash, hash('sha256', $code))) {
            DB::run('
UPDATE `TwoFactorCodes`
    SET `attempts` = `attempts` + 1
    WHERE `codeId` = ?
', 'i', $stored_code -> codeId);

            return false;
        }

        self::clear($user_id);

        return true;
    }

    private static function clear(int $user_id): void
    {
        DB::run('
DELETE
    FROM `TwoFactorCodes`
    WHERE `userId` = ?
', 'i', $user_id);
    }

    /**
     * A zero-padded 6-digit code (000000-999999), drawn from a CSPRNG - not
     * mt_rand, since this is a security credential.
     */
    private static function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
