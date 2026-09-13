<?php

declare(strict_types=1);

/** Authenticated internal connection control; application payloads never enter this protocol. */
class WSRevocation
{
    private array $seen = [];
    private array $revocations = [];

    public static function issue(int $user_id, string $scope, int|string $value): string
    {
        $secret = Config::get('WSSecret');

        if (!is_string($secret) || $secret === '') {
            return '';
        }

        $body = base64_encode(json_encode([
            'userId' => $user_id, 'scope' => $scope, 'value' => $value,
            'issuedAt' => time(), 'nonce' => bin2hex(random_bytes(16)),
        ], JSON_THROW_ON_ERROR));

        return json_encode([
            'control' => $body,
            'signature' => hash_hmac('sha256', 'glommer.ws.revoke.v1.' . $body, $secret),
        ], JSON_THROW_ON_ERROR);
    }

    /** Only the loopback listener calls this; invalid/replayed commands change no state. */
    public function accept(string $line, ?string $secret): ?int
    {
        if ($secret === null || $secret === '' || strlen($line) > 2048) {
            return null;
        }

        $request = json_decode($line, true);

        if (!is_array($request) || count($request) !== 2
            || !is_string($request['control'] ?? null) || !is_string($request['signature'] ?? null)
            || !hash_equals(hash_hmac('sha256', 'glommer.ws.revoke.v1.' . $request['control'], $secret), $request['signature'])) {
            return null;
        }

        $decoded = base64_decode($request['control'], true);
        $command = $decoded === false ? null : json_decode($decoded, true);
        $now = time();

        if (!is_array($command) || count($command) !== 5
            || !is_int($command['userId'] ?? null) || $command['userId'] <= 0
            || !in_array($command['scope'] ?? null, ['version', 'session', 'device'], true)
            || !is_int($command['issuedAt'] ?? null) || $command['issuedAt'] > $now
            || $command['issuedAt'] <= $now - WSToken::TTL_SECONDS
            || !is_string($command['nonce'] ?? null) || preg_match('/\A[0-9a-f]{32}\z/D', $command['nonce']) !== 1) {
            return null;
        }

        $value = $command['value'] ?? null;

        if ($command['scope'] === 'version'
            ? (!is_int($value) || $value <= 0)
            : !WSToken::isIdentity($value)) {
            return null;
        }

        $this -> prune();

        if (isset($this -> seen[$command['nonce']])) {
            return null;
        }

        $expiry = $command['issuedAt'] + WSToken::TTL_SECONDS;
        $this -> seen[$command['nonce']] = $expiry;
        $this -> revocations[$command['userId']][$command['scope']][$value] = max(
            $expiry, $this -> revocations[$command['userId']][$command['scope']][$value] ?? 0
        );

        return $command['userId'];
    }

    public function allows(array $claims): bool
    {
        $now = time();

        if ($claims['expiresAt'] <= $now) {
            return false;
        }

        $rules = $this -> revocations[$claims['userId']] ?? [];

        foreach ($rules['version'] ?? [] as $version => $expiry) {
            if ($expiry > $now && $claims['version'] < $version) {
                return false;
            }
        }

        return ($rules['session'][$claims['sessionId']] ?? 0) <= $now
            && ($claims['deviceId'] === null || ($rules['device'][$claims['deviceId']] ?? 0) <= $now);
    }

    public function prune(): void
    {
        $now = time();
        $this -> seen = array_filter($this -> seen, static fn (int $expiry): bool => $expiry > $now);

        foreach ($this -> revocations as $user_id => $scopes) {
            foreach ($scopes as $scope => $values) {
                $remaining = array_filter($values, static fn (int $expiry): bool => $expiry > $now);

                if ($remaining === []) {
                    unset($this -> revocations[$user_id][$scope]);
                } else {
                    $this -> revocations[$user_id][$scope] = $remaining;
                }
            }

            if ($this -> revocations[$user_id] === []) {
                unset($this -> revocations[$user_id]);
            }
        }
    }
}
