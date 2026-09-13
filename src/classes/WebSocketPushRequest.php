<?php

declare(strict_types=1);

class WebSocketPushRequest
{
    public int $userId;
    public mixed $payload;
    private static array $seen = [];
    private static int $lastPruned = 0;

    public static function encode(int $user_id, array $payload): string
    {
        $secret = Config::get('WSSecret');

        if (!is_string($secret) || $secret === '') {
            return '';
        }

        $json = json_encode([
            'userId' => $user_id, 'payload' => $payload,
            'issuedAt' => time(), 'nonce' => bin2hex(random_bytes(16)),
        ]);

        if (!is_string($json)) {
            return '';
        }

        $body = base64_encode($json);

        return json_encode([
            'push' => $body,
            'signature' => hash_hmac('sha256', 'glommer.ws.push.v1.' . $body, $secret),
        ], JSON_THROW_ON_ERROR);
    }

    public static function fromJSON(string $json, ?string $expected_secret): ?self
    {
        $envelope = json_decode($json, true);

        if (!is_array($envelope) || count($envelope) !== 2
            || !is_string($envelope['push'] ?? null) || !is_string($envelope['signature'] ?? null)
            || $expected_secret === null || $expected_secret === ''
            || !hash_equals(hash_hmac('sha256', 'glommer.ws.push.v1.' . $envelope['push'], $expected_secret), $envelope['signature'])) {
            return null;
        }

        $decoded = base64_decode($envelope['push'], true);
        $request = $decoded === false ? null : json_decode($decoded, true);
        $now = time();

        if (
            !is_array($request)
            || count($request) !== 4
            || !isset($request['userId'], $request['payload'])
            || !is_int($request['issuedAt'] ?? null) || $request['issuedAt'] > $now || $request['issuedAt'] <= $now - 30
            || !is_string($request['nonce'] ?? null) || preg_match('/\A[0-9a-f]{32}\z/D', $request['nonce']) !== 1
            || !(is_int($request['userId']) || (is_string($request['userId']) && ctype_digit($request['userId'])))
            || (int) $request['userId'] <= 0
        ) {
            return null;
        }

        if (self::$lastPruned !== $now) {
            self::$seen = array_filter(self::$seen, static fn (int $expiry): bool => $expiry > $now);
            self::$lastPruned = $now;
        }

        if (isset(self::$seen[$request['nonce']])) {
            return null;
        }

        self::$seen[$request['nonce']] = $request['issuedAt'] + 30;

        $push = new self();
        $push -> userId = (int) $request['userId'];
        $push -> payload = $request['payload'];

        return $push;
    }
}
