<?php

declare(strict_types=1);

class EmailVerification
{
    public ?int $verificationId = null;
    public ?int $userId = null;
    public ?string $tokenHash = null;
    public ?string $expiresAt = null;
    public ?string $createdAt = null;

    /** How long the link is good for, said in the mail and enforced in the DB row alike. */
    private const EXPIRY_HOURS = 24;

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
        $token = self::create((int) $user -> userId, (string) $user -> email);

        if ($token === null) {
            return false;
        }

        $verify_url = ServerURL::absolute('/verify-email?token=' . $token);

        $name = $user -> title ?: $user -> slug;

        // Read in the recipient's own language, not whoever is currently
        // browsing - the two are unrelated for a signup or an email change,
        // and this may run with no browser attached to it at all.
        Strings::useLocale($user -> locale ?? Strings::SOURCE_LOCALE);

        try {
            $words = Strings::for(self::class);
            $subject = (string) ($words['subject'] ?? '');
            $expiry = str_replace('{count}', (string) self::EXPIRY_HOURS, Strings::plural(self::class, 'expiresIn', self::EXPIRY_HOURS));

            $text_body = str_replace('{name}', $name, (string) ($words['greeting'] ?? '')) . chr(10) . chr(10)
                . (string) ($words['instructions'] ?? '') . chr(10)
                . $verify_url . chr(10) . chr(10)
                . $expiry;

            $html_body = '<p>' . str_replace('{name}', htmlspecialchars($name), (string) ($words['greeting'] ?? '')) . '</p>'
                . '<p>' . htmlspecialchars((string) ($words['htmlInstructions'] ?? '')) . '</p>'
                . '<p><a href="' . htmlspecialchars($verify_url) . '">' . htmlspecialchars((string) ($words['buttonLabel'] ?? '')) . '</a></p>'
                . '<p>' . htmlspecialchars($expiry) . '</p>';
        } finally {
            Strings::useLocale(null);
        }

        $sent = Mailer::send($user -> email, $name, $subject, $text_body, $html_body);

        // Only auto-verify when mail delivery itself is broken/unconfigured -
        // not when this specific address was rejected (Mailer::attempt()
        // reached the destination server fine, and it refused the recipient).
        // Otherwise anyone could bypass verification by signing up with an
        // address engineered to bounce.
        if (!$sent && !Mailer::recipientWasRejected()) {
            // Rather than leaving the user permanently stuck behind the
            // verification gate with no way to ever receive the link that
            // would clear it, verify them directly instead.
            $verified = self::markVerified((int) $user -> userId, (string) $user -> email);

            // Let the admin know the mailer is down so they can fix it
            // (throttled, so a flood of failures doesn't pile up). A
            // from-address-not-configured failure already got its own more
            // specific notification straight from Mailer::send() - this is
            // deliberately still sent too (a different, broader signal: mail
            // delivery in general isn't working, whatever the exact cause).
            Notification::warnAdminMailerFailed((int) $user -> userId);

            return $verified;
        }

        return false;
    }

    public static function verify(string $token): ?int
    {
        $token_hash = hash('sha256', $token);

        $verification = DB::row('
SELECT `userId`
    FROM `EmailVerifications`
    WHERE `tokenHash` = ? AND `expiresAt` > NOW()
', 'EmailVerificationData', 's', $token_hash);

        if ($verification === null) {
            return null;
        }

        $user_id = (int) $verification -> userId;

        return DB::transaction(static function () use ($user_id, $token_hash): ?int {
            $user = User::loadForUpdate($user_id);

            if ($user === null) {
                return null;
            }

            $current = DB::row('
SELECT `userId`
    FROM `EmailVerifications`
    WHERE `userId` = ? AND `tokenHash` = ? AND `expiresAt` > NOW()
    FOR UPDATE
', 'EmailVerificationData', 'is', $user_id, $token_hash);

            if ($current === null) {
                return null;
            }

            self::markVerified($user_id, (string) $user -> email);
            self::purgeForUser($user_id);

            return $user_id;
        });
    }

    public static function purgeForUser(int $user_id): void
    {
        DB::run('
DELETE
    FROM `EmailVerifications`
    WHERE `userId` = ?
', 'i', $user_id);
    }

    private static function markVerified(int $user_id, string $expected_email): bool
    {
        $verified = 1;

        $updated = DB::run('
UPDATE `Users`
    SET `verified` = ?
    WHERE `userId` = ? AND `email` = ?
', 'iis', $verified, $user_id, $expected_email);

        return mysqli_stmt_affected_rows($updated) === 1;
    }

    private static function create(int $user_id, ?string $expected_email = null): ?string
    {
        $token = bin2hex(random_bytes(32));
        $token_hash = hash('sha256', $token);

        DB::run('
DELETE
    FROM `EmailVerifications`
    WHERE `expiresAt` <= NOW()
');

        return DB::transaction(static function () use ($user_id, $expected_email, $token, $token_hash): ?string {
            $user = User::loadForUpdate($user_id);

            if ($user === null || ($expected_email !== null && strcasecmp((string) $user -> email, $expected_email) !== 0)) {
                return null;
            }

            DB::run('
INSERT INTO `EmailVerifications` (`userId`, `tokenHash`, `expiresAt`)
    VALUES (?, ?, NOW() + INTERVAL ? HOUR)
', 'isi', $user_id, $token_hash, self::EXPIRY_HOURS);

            return $token;
        });
    }
}
