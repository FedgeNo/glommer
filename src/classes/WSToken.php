<?php

declare(strict_types=1);

/**
 * Short-lived signed tokens that authenticate a WebSocket connection to
 * bin/websocket-server.php - a process entirely separate from Apache/PHP-FPM
 * that doesn't share PHP's session handling. A browser's WebSocket API can't
 * set custom headers on the handshake, so the token travels as a query
 * string parameter on the connection URL instead (the standard approach for
 * browser-originated WebSocket auth) and is verified statelessly against the
 * shared WS_SECRET - no DB/session lookup needed on the daemon side.
 */
class WSToken
{
    private const TTL_SECONDS = 60;

    public static function issue(int $user_id): string
    {
        $config = require __DIR__ . '/../config.php';

        // No secret configured - hand out no usable token rather than sign
        // with an absent key. WS auth is off until WS_SECRET is set.
        if (!is_string($config['WSSecret']) || $config['WSSecret'] === '') {
            return '';
        }

        $expires_at = time() + self::TTL_SECONDS;
        $payload = $user_id . '.' . $expires_at;
        $signature = hash_hmac('sha256', $payload, $config['WSSecret']);

        return $payload . '.' . $signature;
    }

    /**
     * @param ?string $secret the daemon reads WS_SECRET itself rather than
     *                        loading config.php's whole array, so this takes
     *                        the secret directly rather than via config()
     */
    public static function verify(string $token, ?string $secret): ?int
    {
        // No secret configured - reject every token (fail closed) rather than
        // let hash_hmac run on a null/empty key.
        if ($secret === null || $secret === '') {
            return null;
        }

        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        [$user_id, $expires_at, $signature] = $parts;

        if (!ctype_digit($user_id) || !ctype_digit($expires_at)) {
            return null;
        }

        if ((int) $expires_at < time()) {
            return null;
        }

        $expected_signature = hash_hmac('sha256', $user_id . '.' . $expires_at, $secret);

        if (!hash_equals($expected_signature, $signature)) {
            return null;
        }

        return (int) $user_id;
    }
}
