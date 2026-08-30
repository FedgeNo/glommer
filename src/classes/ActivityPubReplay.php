<?php

declare(strict_types=1);

/**
 * Remembers the signatures the inbox has already accepted, so one cannot be
 * used twice.
 *
 * A signature stays valid for as long as its Date header is fresh, which is an
 * hour - and freshness is the only thing bounding it. Anyone positioned to
 * capture one delivery (a proxy, a log, a mistakenly shared trace) could
 * otherwise post it again within that hour and have it accepted as genuine,
 * because it is genuine: it is the same request, signed by the same server.
 * The only thing that distinguishes the second from the first is that we have
 * seen it before, so that is what gets written down.
 *
 * A repeat is answered 202, not refused: a sender that never received our
 * first answer is entitled to try again, and there is nothing to gain by
 * treating a duplicate as an attack when ignoring it is the same outcome.
 */
class ActivityPubReplay
{
    public ?string $signatureHash = null;
    public ?string $createdAt = null;

    /** Matches HTTPSignature's date-skew window, which is what bounds a replay. */
    private const RETENTION_SECONDS = 3600;

    /**
     * Records this signature and reports whether it had already been seen.
     * The insert is the check: the primary key is what makes two callers
     * racing the same signature resolve to one winner.
     */
    public static function seenBefore(string $signature_header): bool
    {
        $signature = HTTPSignature::parseSignatureHeader($signature_header);

        // Verification has already parsed and accepted this header before the
        // replay check runs. Hash its meaning rather than its wire formatting:
        // parameter order and insignificant separators can change without
        // changing the signature and must not create a fresh replay identity.
        // Keep a raw-header fallback so this class still fails safely if a
        // caller ever invokes it before verification.
        $identity = $signature === null
            ? $signature_header
            : json_encode([
                'keyId' => $signature['keyId'],
                'algorithm' => strtolower($signature['algorithm']),
                'headers' => strtolower(implode(' ', preg_split('/\\s+/', trim($signature['headers'])) ?: [])),
                'signature' => $signature['signature'],
            ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $hash = hash('sha256', $identity);

        try {
            DB::run('
INSERT INTO `ActivityPubReplays` (`signatureHash`)
    VALUES (?)
', 's', $hash);
        } catch (\mysqli_sql_exception $exception) {
            // 1062 = the primary key rejected a signature already recorded,
            // which is exactly the thing being detected.
            if ($exception -> getCode() === 1062) {
                return true;
            }

            throw $exception;
        }

        self::sweep();

        return false;
    }

    /**
     * Drops rows past the window they could still be replayed in. Occasional
     * rather than scheduled, the same lottery RateLimiter uses - the table only
     * ever needs to hold an hour of deliveries.
     */
    private static function sweep(): void
    {
        if (mt_rand(1, 100) !== 1) {
            return;
        }

        $retention = self::RETENTION_SECONDS;

        DB::run('
DELETE
    FROM `ActivityPubReplays`
    WHERE `createdAt` < NOW() - INTERVAL ? SECOND
', 'i', $retention);
    }
}
