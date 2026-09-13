<?php

declare(strict_types=1);

/** Signed, expiring connection leases. The daemon verifies these without a database. */
class WSToken
{
    public const TTL_SECONDS = 30;

    public static function issue(int $user_id, int $version, string $session_id, ?string $device_id = null, ?int $issued_at = null): string
    {
        $secret = Config::get('WSSecret');

        // No secret configured - hand out no usable token rather than sign
        // with an absent key. WS auth is off until WS_SECRET is set.
        if (!is_string($secret) || $secret === '') {
            return '';
        }

        $issued_at ??= time();
        $body = base64_encode(json_encode([
            'userId' => $user_id, 'version' => $version, 'sessionId' => $session_id,
            'deviceId' => $device_id, 'issuedAt' => $issued_at,
            'expiresAt' => $issued_at + self::TTL_SECONDS,
        ], JSON_THROW_ON_ERROR));

        return $body . '.' . hash_hmac('sha256', 'glommer.ws.lease.v1.' . $body, $secret);
    }

    /**
     * @param ?string $secret the daemon reads WS_SECRET itself rather than
     *                        loading config.php's whole array, so this takes
     *                        the secret directly rather than via config()
     */
    public static function verify(string $token, ?string $secret): ?array
    {
        // No secret configured - reject every token (fail closed) rather than
        // let hash_hmac run on a null/empty key.
        if ($secret === null || $secret === '' || strlen($token) > 2048) {
            return null;
        }

        $parts = explode('.', $token);

        if (count($parts) !== 2 || !hash_equals(hash_hmac('sha256', 'glommer.ws.lease.v1.' . $parts[0], $secret), $parts[1])) {
            return null;
        }

        $decoded = base64_decode($parts[0], true);
        $claims = $decoded === false ? null : json_decode($decoded, true);
        $now = time();

        if (!is_array($claims) || count($claims) !== 6
            || !is_int($claims['userId'] ?? null) || $claims['userId'] <= 0
            || !is_int($claims['version'] ?? null) || $claims['version'] < 0
            || !self::isIdentity($claims['sessionId'] ?? null)
            || !array_key_exists('deviceId', $claims)
            || ($claims['deviceId'] !== null && !self::isIdentity($claims['deviceId']))
            || !is_int($claims['issuedAt'] ?? null) || $claims['issuedAt'] < 0 || $claims['issuedAt'] > $now
            || !is_int($claims['expiresAt'] ?? null) || $claims['expiresAt'] <= $now
            || $claims['expiresAt'] - $claims['issuedAt'] > self::TTL_SECONDS) {
            return null;
        }

        return $claims;
    }

    public static function isIdentity(mixed $value): bool
    {
        return is_string($value) && preg_match('/\A[0-9a-f]{64}\z/D', $value) === 1;
    }
}
