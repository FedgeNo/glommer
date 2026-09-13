<?php

declare(strict_types=1);

/** Transport-only capacity accounting. No database or client-supplied identity. */
class WebSocketAdmission
{
    public static function limit(string $name, int $default): int
    {
        $value = Env::get($name);
        return is_string($value) && ctype_digit($value) && (int) $value > 0 ? (int) $value : $default;
    }

    public static function peerIP(string $peer): string
    {
        $host = str_starts_with($peer, '[')
            ? substr($peer, 1, (int) strpos($peer, ']') - 1)
            : substr($peer, 0, (int) strrpos($peer, ':'));
        $packed = @inet_pton($host);
        if ($packed === false) {
            return 'unknown';
        }
        if (strlen($packed) === 16 && substr($packed, 0, 12) === str_repeat("\0", 10) . "\xff\xff") {
            $packed = substr($packed, 12);
        }
        return (string) inet_ntop($packed);
    }

    public static function allows(array $connections, string $kind, string $ip): bool
    {
        $public = $pending = $same_ip = $internal = 0;
        foreach ($connections as $connection) {
            if ($connection['kind'] === 'push') {
                $internal++;
            } else {
                $public++;
                if ($connection['userId'] === null) {
                    $pending++;
                    if (($connection['peerIP'] ?? 'unknown') === $ip) {
                        $same_ip++;
                    }
                }
            }
        }
        if ($kind === 'push') {
            return $internal < self::limit('WS_MAX_INTERNAL_CONNECTIONS', 64);
        }
        return $public < self::limit('WS_MAX_CONNECTIONS', 500)
            && $pending < self::limit('WS_MAX_PENDING_CONNECTIONS', 50)
            && $same_ip < self::limit('WS_MAX_PENDING_PER_IP', 10);
    }
}
