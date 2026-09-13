<?php

declare(strict_types=1);

/** Bounded pre-authentication discovery, including unknown keys and key rotation. */
class ActorDiscovery
{
    public const DEADLINE_SECONDS = 20;
    public const MAX_CONCURRENT = 4;
    public const PER_IP_PER_MINUTE = 30;

    public static function resolve(string $uri, string $ip, float $deadline, bool $refresh = false): ?User
    {
        if (!$refresh) {
            $existing = User::byRemoteActorURI($uri);
            if ($existing !== null && $existing -> remoteActorPublicKeyPem !== null) {
                return $existing;
            }
        }
        if (strlen($uri) > 255 || !URL::isValidHTTPURL($uri) || ActivityPubActor::isLocalActorURI($uri)) {
            return null;
        }
        if (self::failedRecently($uri)) {
            throw new ActorDiscoveryBusy('Actor discovery is cooling down.');
        }
        $remaining = min(self::DEADLINE_SECONDS, $deadline - hrtime(true) / 1e9);
        if ($remaining < 0.001) {
            throw new ActorDiscoveryBusy('Actor discovery deadline exceeded.');
        }
        // PHP_BINARY can be php-fpm under web requests. Use the CLI sibling.
        // GNU timeout is already required by the media processors. It also
        // kills a blocked DNS resolver, which a curl timeout cannot cover.
        $process = proc_open(array_merge(['timeout', '--signal=KILL', number_format($remaining, 3, '.', '') . 's'],
            static::childCommand($uri, $ip, $refresh)),
            [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new ActorDiscoveryBusy('Actor discovery could not start.');
        }
        // Keep the parent supervising until timeout/completion even if the
        // HTTP sender disconnects. The child holds its own database locks.
        $previous_abort = ignore_user_abort(true);
        try {
            $output = stream_get_contents($pipes[1], 4097);
            fclose($pipes[1]);
            $status = proc_close($process);
        } finally {
            ignore_user_abort((bool) $previous_abort);
        }
        $result = is_string($output) && strlen($output) <= 4096 ? json_decode($output, true) : null;
        if ($status !== 0 || !is_array($result)) {
            self::recordFailure($uri);
            throw new ActorDiscoveryBusy('Actor discovery failed or timed out.');
        }
        if (($result['status'] ?? '') === 'busy') {
            throw new ActorDiscoveryBusy('Actor discovery capacity is unavailable.');
        }
        return ($result['status'] ?? '') === 'ok' ? User::byRemoteActorURI($uri) : null;
    }

    /** Runs in the bounded child. Locks disappear if it exits or is killed. */
    public static function discover(string $uri, string $ip, bool $refresh = false): array
    {
        if (strlen($uri) > 255 || !URL::isValidHTTPURL($uri) || ActivityPubActor::isLocalActorURI($uri) || SetupClaim::required()) {
            return ['status' => 'missing'];
        }
        $actor_lock = self::lockName('actor:' . $uri);
        if (!self::tryLock($actor_lock)) {
            return ['status' => 'busy'];
        }
        $slot = null;
        try {
            if (!$refresh) {
                $existing = User::byRemoteActorURI($uri);
                if ($existing !== null && $existing -> remoteActorPublicKeyPem !== null) {
                    return ['status' => 'ok'];
                }
            }
            if (self::failedRecently($uri)) {
                return ['status' => 'busy'];
            }
            for ($index = 0; $index < self::MAX_CONCURRENT; $index++) {
                $name = self::lockName('slot:' . $index);
                if (self::tryLock($name)) {
                    $slot = $name;
                    break;
                }
            }
            if ($slot === null) {
                return ['status' => 'busy'];
            }
            $rate_key = 'actor-discovery:' . $ip;
            if (RateLimiter::tooManyAttempts($rate_key, self::PER_IP_PER_MINUTE, 60, 0)) {
                return ['status' => 'busy'];
            }
            RateLimiter::recordAttempt($rate_key);
            if ($refresh) {
                // Capacity refusals must not consume the actor's rotation
                // allowance. Reserve it only once this child can fetch.
                $refresh_key = 'activitypub-key-refresh:' . $uri;
                if (RateLimiter::tooManyAttempts($refresh_key, 1, 300, 0)) {
                    return ['status' => 'missing'];
                }
                RateLimiter::recordAttempt($refresh_key);
            }
            $actor = static::fetch($uri);
            if ($actor === null || $actor['id'] !== $uri) {
                self::recordFailure($uri);
                return ['status' => 'busy'];
            }
            RemoteActor::upsert($actor);
            DB::run('DELETE FROM `ActorDiscoveryFailures` WHERE `actorHash` = ?', 's', hash('sha256', $uri));
            return ['status' => 'ok'];
        } catch (RateLimitLockException $exception) {
            return ['status' => 'busy'];
        } finally {
            if ($slot !== null) {
                self::release($slot);
            }
            self::release($actor_lock);
        }
    }

    protected static function fetch(string $uri): ?array
    {
        return RemoteActor::fetch($uri);
    }

    protected static function childCommand(string $uri, string $ip, bool $refresh): array
    {
        return [PHP_BINDIR . '/php', __DIR__ . '/../../bin/discover-actor.php', $uri, $ip, $refresh ? '1' : '0'];
    }

    private static function failedRecently(string $uri): bool
    {
        return DB::row('SELECT `actorHash` FROM `ActorDiscoveryFailures` WHERE `actorHash` = ? AND `expiresAt` > NOW()',
            \stdClass::class, 's', hash('sha256', $uri)) !== null;
    }

    private static function recordFailure(string $uri): void
    {
        DB::run('INSERT INTO `ActorDiscoveryFailures` (`actorHash`, `expiresAt`) VALUES (?, NOW() + INTERVAL 1 MINUTE)
            ON DUPLICATE KEY UPDATE `expiresAt` = VALUES(`expiresAt`)', 's', hash('sha256', $uri));
    }

    public static function prune(): void
    {
        DB::run('DELETE FROM `ActorDiscoveryFailures` WHERE `expiresAt` <= NOW() ORDER BY `expiresAt` LIMIT 1000');
    }

    private static function lockName(string $scope): string
    {
        return 'actor-discovery:' . md5((string) Config::get('database') . "\0" . $scope);
    }

    private static function tryLock(string $name): bool
    {
        $row = DB::row('SELECT GET_LOCK(?, 0) AS `acquired`', \stdClass::class, 's', $name);
        return (int) $row -> acquired === 1;
    }

    private static function release(string $name): void
    {
        DB::run('SELECT RELEASE_LOCK(?)', 's', $name);
    }
}
