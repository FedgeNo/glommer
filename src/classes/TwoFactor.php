<?php

declare(strict_types=1);

/**
 * Opt-in, email-only two-factor authentication. When a user turns it on, a
 * password login no longer completes on the password alone: a short-lived
 * numeric code is emailed to their (already verified) address, and login
 * only finishes once that code is entered (api/login.php ->
 * api/verify-2fa.php). Codes have a SHA-256 verifier and a random seed whose
 * code can only be derived with the server secret. One active code per user,
 * reused without resetting its expiry or attempt count. Codes expire
 * quickly, and are attempt-capped so the short numeric space can't be
 * brute-forced within a code's lifetime.
 *
 * Deliberately email-only (no TOTP/SMS): it reuses the verified-email
 * channel the site already has and needs no authenticator-app enrollment.
 * 2FA fails closed - a mail outage never downgrades a login to
 * password-only. The escape hatch is a batch of single-use recovery codes
 * issued when 2FA is turned on, any of which finishes a login in place of
 * the emailed code.
 */
class TwoFactor
{
    private const CODE_TTL_MINUTES = 10;
    private const MAX_ATTEMPTS = 5;
    private const RECOVERY_CODE_COUNT = 10;
    private const RECOVERY_CODE_BYTES = 5;

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

        // A remembered browser is a persistent authentication credential. Any
        // one issued before 2FA was enabled has never completed the new factor,
        // so enabling it revokes every remembered device. The current session
        // remains signed in and can issue a fresh token only through the normal
        // password-plus-2FA login flow.
        if ($enabled) {
            RememberToken::purgeForUser($user_id);
        }

        // Turning it off leaves no reason to keep a pending code or the
        // recovery codes around.
        if (!$enabled) {
            self::clear($user_id);
            self::clearRecoveryCodes($user_id);
        }
    }

    /**
     * Issues a fresh batch of single-use recovery codes for the user,
     * replacing any unused ones, and returns them in plain text - the one and
     * only time they exist unhashed, so the caller must show them now.
     *
     * @return string[]
     */
    public static function generateRecoveryCodes(int $user_id): array
    {
        self::clearRecoveryCodes($user_id);

        $codes = [];

        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; $i++) {
            // 40 bits of CSPRNG entropy per code, far beyond guessing within
            // any rate limit. Hyphenated for readability; verification
            // strips the hyphen back out.
            $raw = bin2hex(random_bytes(self::RECOVERY_CODE_BYTES));
            $code = substr($raw, 0, 5) . '-' . substr($raw, 5);
            $code_hash = hash('sha256', $raw);

            DB::run('
INSERT INTO `TwoFactorRecoveryCodes` (`userId`, `codeHash`)
    VALUES (?, ?)
', 'is', $user_id, $code_hash);

            $codes[] = $code;
        }

        return $codes;
    }

    /**
     * Checks a submitted recovery code and consumes it (deletes the row) on a
     * match, so each code works exactly once. Tolerant of the display
     * formatting: hyphens, spaces, and letter case don't matter.
     */
    public static function verifyRecoveryCode(int $user_id, string $code): bool
    {
        $normalized = strtolower((string) preg_replace('/[\s-]+/', '', $code));

        if ($normalized === '') {
            return false;
        }

        $code_hash = hash('sha256', $normalized);

        $stored_code = DB::row('
SELECT `recoveryCodeId`
    FROM `TwoFactorRecoveryCodes`
    WHERE `userId` = ? AND `codeHash` = ?
', 'stdClass', 'is', $user_id, $code_hash);

        if ($stored_code === null) {
            return false;
        }

        DB::run('
DELETE
    FROM `TwoFactorRecoveryCodes`
    WHERE `recoveryCodeId` = ?
', 'i', $stored_code -> recoveryCodeId);

        return true;
    }

    /** How many unused recovery codes the user has left. */
    public static function recoveryCodesRemaining(int $user_id): int
    {
        $row = DB::row('
SELECT COUNT(*) AS `remaining`
    FROM `TwoFactorRecoveryCodes`
    WHERE `userId` = ?
', 'stdClass', 'i', $user_id);

        return (int) ($row -> remaining ?? 0);
    }

    private static function clearRecoveryCodes(int $user_id): void
    {
        DB::run('
DELETE
    FROM `TwoFactorRecoveryCodes`
    WHERE `userId` = ?
', 'i', $user_id);
    }

    /** Returns sent, ready (already emailed), limited, or failed; never authenticates. */
    public static function sendCode(User $user): string
    {
        $user_id = (int) $user -> userId;
        $reservation = DB::transaction(static function () use ($user, $user_id): array|string {
            // Serialize issuance across browsers, and against code consumption.
            $current = DB::row('SELECT * FROM `Users` WHERE `userId` = ? FOR UPDATE', 'User', 'i', $user_id);
            if ($current === null || $current -> banned || !$current -> twoFactorEnabled
                || $current -> sessionVersion !== $user -> sessionVersion) {
                return 'failed';
            }

            DB::run('DELETE FROM `TwoFactorEmails` WHERE `userId` = ? AND `createdAt` <= NOW() - INTERVAL 15 MINUTE', 'i', $user_id);
            $recent = DB::row('
SELECT COUNT(*) AS `total`, COALESCE(MAX(`createdAt`) > NOW() - INTERVAL 1 MINUTE, 0) AS `cooldown`
    FROM `TwoFactorEmails` WHERE `userId` = ?
', 'stdClass', 'i', $user_id);
            $stored = DB::row('SELECT * FROM `TwoFactorCodes` WHERE `userId` = ? AND `expiresAt` > NOW()', 'stdClass', 'i', $user_id);
            $ready = $stored !== null && (bool) $stored -> emailed && (int) $stored -> attempts < self::MAX_ATTEMPTS;
            if ((int) $recent -> total >= 3 || (bool) $recent -> cooldown) {
                return $ready ? 'ready' : 'limited';
            }
            if ($stored !== null) {
                // A burned or legacy code remains in place until expiry. An
                // upgrade or secret rotation must not invalidate an emailed code.
                $code = self::codeFor($user_id, (string) ($stored -> codeSeed ?? ''));
                if ((int) $stored -> attempts >= self::MAX_ATTEMPTS || $code === null
                    || !hash_equals($stored -> codeHash, hash('sha256', $code))) {
                    return $ready ? 'ready' : 'limited';
                }
            } else {
                $seed = bin2hex(random_bytes(32));
                $code = self::codeFor($user_id, $seed);
                if ($code === null) {
                    return 'failed';
                }
                DB::run('
INSERT INTO `TwoFactorCodes` (`userId`, `codeHash`, `codeSeed`, `expiresAt`)
    VALUES (?, ?, ?, NOW() + INTERVAL ? MINUTE)
    ON DUPLICATE KEY UPDATE `codeHash` = VALUES(`codeHash`), `codeSeed` = VALUES(`codeSeed`),
        `expiresAt` = VALUES(`expiresAt`), `attempts` = 0, `emailed` = 0, `createdAt` = NOW()
', 'issi', $user_id, hash('sha256', $code), $seed, self::CODE_TTL_MINUTES);
            }
            // Reserve before SMTP, including failures. Mail runs outside the
            // transaction; a hung mailer cannot hold an account's database lock.
            DB::run('INSERT INTO `TwoFactorEmails` (`userId`) VALUES (?)', 'i', $user_id);
            return [$code];
        });

        if (is_string($reservation)) {
            return $reservation;
        }
        [$code] = $reservation;
        $sent = static::deliverCode($user, $code);
        if ($sent) {
            // A code consumed while mail was in flight must stay consumed.
            DB::run('UPDATE `TwoFactorCodes` SET `emailed` = 1 WHERE `userId` = ? AND `codeHash` = ?',
                'is', $user_id, hash('sha256', $code));
        }
        return $sent ? 'sent' : 'failed';
    }

    protected static function deliverCode(User $user, string $code): bool
    {
        $name = $user -> title ?: $user -> slug;

        $text_body = 'Hi ' . $name . ',

Your login verification code is: ' . $code . '

It expires ' . self::CODE_TTL_MINUTES . ' minutes after it was first issued. Requesting it again does not extend that time. If you didn\'t just try to log in, someone may have your password - change it as soon as you can.';

        $html_body = '<p>Hi ' . htmlspecialchars($name) . ',</p>'
            . '<p>Your login verification code is:</p>'
            . '<p style="font-size: 1.5em; font-weight: bold; letter-spacing: 0.2em;">' . htmlspecialchars($code) . '</p>'
            . '<p>It expires ' . self::CODE_TTL_MINUTES . ' minutes after it was first issued. Requesting it again does not extend that time. If you didn\'t just try to log in, someone may have your password - change it as soon as you can.</p>';

        return Mailer::send($user -> email, $name, 'Your login verification code', $text_body, $html_body);
    }

    /**
     * Checks a submitted code for the user. Returns true only on an exact,
     * unexpired, under-the-attempt-cap match - and consumes the code (deletes
     * the row) on success so it can't be replayed. A wrong guess increments
     * the attempt counter; once it hits MAX_ATTEMPTS the code stays burned until
     * expiry, so restarting login cannot reset its guessing budget.
     */
    public static function verifyCode(int $user_id, string $code): bool
    {
        return DB::transaction(static function () use ($user_id, $code): bool {
            DB::row('SELECT `userId` FROM `Users` WHERE `userId` = ? FOR UPDATE', 'stdClass', 'i', $user_id);
            return self::consumeCode($user_id, $code);
        });
    }

    private static function consumeCode(int $user_id, string $code): bool
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

    /** A random seed alone cannot reveal the code in a database-only leak. */
    private static function codeFor(int $user_id, string $seed): ?string
    {
        $secret = (string) Config::get('WSSecret');
        if ($secret === '' || preg_match('/\A[a-f0-9]{64}\z/', $seed) !== 1) {
            return null;
        }
        // Rejection sampling keeps all six-digit values equally likely.
        for ($counter = 0; ; $counter++) {
            $hash = hash_hmac('sha256', 'glommer.2fa.email.v1.' . $user_id . '.' . $seed . '.' . $counter, $secret);
            $number = hexdec(substr($hash, 0, 8));
            if ($number < 4294000000) {
                return str_pad((string) ($number % 1000000), 6, '0', STR_PAD_LEFT);
            }
        }
    }
}
