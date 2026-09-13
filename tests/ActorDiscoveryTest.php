<?php

declare(strict_types=1);

class FixtureActorDiscovery extends ActorDiscovery
{
    public static int $fetches = 0;
    public static bool $fail = false;

    protected static function fetch(string $uri): ?array
    {
        self::$fetches++;
        if (self::$fail) return null;
        return ['id' => $uri, 'inbox' => $uri . '/inbox', 'sharedInbox' => null,
            'publicKeyPem' => 'fixture-key', 'preferredUsername' => 'fixture', 'name' => 'Fixture'];
    }
}

class StalledActorDiscovery extends ActorDiscovery
{
    protected static function childCommand(string $uri, string $ip, bool $refresh): array
    {
        return [PHP_BINARY, '-r', 'usleep(10000000);'];
    }
}

class ActorDiscoveryTest extends DatabaseTestCase
{
    private function withDiscovery(callable $work): void
    {
        $uri = 'https://discovery.example.com/' . bin2hex(random_bytes(8));
        $ip = 'test-' . bin2hex(random_bytes(8));
        FixtureActorDiscovery::$fetches = 0;
        FixtureActorDiscovery::$fail = false;
        try {
            $work($uri, $ip);
        } finally {
            DB::run('DELETE FROM `Users` WHERE `remoteActorURI` = ?', 's', $uri);
            DB::run('DELETE FROM `RateLimitAttempts` WHERE `rateKey` = ?', 's', 'actor-discovery:' . $ip);
            DB::run('DELETE FROM `RateLimitAttempts` WHERE `rateKey` = ?', 's', 'activitypub-key-refresh:' . $uri);
            DB::run('DELETE FROM `ActorDiscoveryFailures` WHERE `actorHash` = ?', 's', hash('sha256', $uri));
        }
    }

    public function testKnownActorAvoidsFetchingAndFailedDiscoveryCoolsDown(): void
    {
        $this -> withDiscovery(function ($uri, $ip): void {
            $this -> assertSame('ok', FixtureActorDiscovery::discover($uri, $ip)['status']);
            $this -> assertSame('ok', FixtureActorDiscovery::discover($uri, $ip)['status']);
            $this -> assertSame(1, FixtureActorDiscovery::$fetches);
            FixtureActorDiscovery::$fail = true;
            $this -> assertSame('busy', FixtureActorDiscovery::discover($uri, $ip, true)['status']);
            $this -> assertSame('busy', FixtureActorDiscovery::discover($uri, $ip, true)['status']);
            $this -> assertSame(2, FixtureActorDiscovery::$fetches);
            $this -> assertNotNull(ActorDiscovery::resolve($uri, $ip, hrtime(true) / 1e9));
        });
    }

    public function testThirtyDiscoveriesUseTheBudgetButCachedActorsRemainUsable(): void
    {
        $this -> withDiscovery(function ($uri, $ip): void {
            $this -> assertSame('ok', FixtureActorDiscovery::discover($uri, $ip)['status']);
            for ($i = 1; $i < 30; $i++) {
                DB::run('INSERT INTO `RateLimitAttempts` (`rateKey`) VALUES (?)', 's', 'actor-discovery:' . $ip);
            }
            $this -> assertSame('busy', FixtureActorDiscovery::discover($uri, $ip, true)['status']);
            $this -> assertSame('ok', FixtureActorDiscovery::discover($uri, $ip)['status']);
            $this -> assertSame(1, FixtureActorDiscovery::$fetches);
        });
    }

    public function testOtherProcessesHoldGlobalSlotsAndTheSameActorWithoutMakingCallersWait(): void
    {
        $this -> withDiscovery(function ($uri, $ip): void {
            $other = mysqli_connect('localhost', 'root', '', (string) Config::get('database'));
            $lock = new \ReflectionMethod(ActorDiscovery::class, 'lockName');
            $names = [];
            try {
                for ($i = 0; $i < 4; $i++) {
                    $name = $lock -> invoke(null, 'slot:' . $i);
                    $names[] = $name;
                    $stmt = mysqli_prepare($other, 'SELECT GET_LOCK(?, 0)');
                    mysqli_stmt_bind_param($stmt, 's', $name);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_get_result($stmt);
                }
                $start = microtime(true);
                $this -> assertSame('busy', FixtureActorDiscovery::discover($uri, $ip)['status']);
                $this -> assertSame('busy', FixtureActorDiscovery::discover($uri, $ip, true)['status']);
                $this -> assertCount(0, DB::rows('SELECT `rateKey` FROM `RateLimitAttempts` WHERE `rateKey` = ?', \stdClass::class, 's', 'activitypub-key-refresh:' . $uri));
                $this -> assertTrue(microtime(true) - $start < 1);
                $this -> assertSame(0, FixtureActorDiscovery::$fetches);
            } finally {
                mysqli_close($other);
            }
            $this -> assertSame('ok', FixtureActorDiscovery::discover($uri, $ip)['status']);
            $other = mysqli_connect('localhost', 'root', '', (string) Config::get('database'));
            try {
                $name = $lock -> invoke(null, 'actor:' . $uri);
                $stmt = mysqli_prepare($other, 'SELECT GET_LOCK(?, 0)');
                mysqli_stmt_bind_param($stmt, 's', $name);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_get_result($stmt);
                $this -> assertSame('busy', FixtureActorDiscovery::discover($uri, $ip, true)['status']);
                $this -> assertSame(1, FixtureActorDiscovery::$fetches);
            } finally {
                mysqli_close($other);
            }
            $this -> assertSame('ok', FixtureActorDiscovery::discover($uri, $ip, true)['status']);
            $this -> assertSame('missing', FixtureActorDiscovery::discover($uri, $ip, true)['status']);
            $this -> assertSame(2, FixtureActorDiscovery::$fetches);
            $this -> assertCount(1, DB::rows('SELECT `rateKey` FROM `RateLimitAttempts` WHERE `rateKey` = ?', \stdClass::class, 's', 'activitypub-key-refresh:' . $uri));
        });
    }

    public function testExpiredOverallDeadlineDoesNotStartDiscovery(): void
    {
        $this -> withDiscovery(function ($uri, $ip): void {
            $this -> assertSame(20, ActorDiscovery::DEADLINE_SECONDS);
            try {
                ActorDiscovery::resolve($uri, $ip, hrtime(true) / 1e9 - 1);
                $this -> assertTrue(false);
            } catch (ActorDiscoveryBusy $exception) {
                $this -> assertTrue(str_contains($exception -> getMessage(), 'deadline'));
            }
            $this -> assertNull(User::byRemoteActorURI($uri));
        });
    }

    public function testWallDeadlineKillsABlockedChildAndCachesTheFailure(): void
    {
        $this -> withDiscovery(function ($uri, $ip): void {
            $start = hrtime(true) / 1e9;
            try {
                StalledActorDiscovery::resolve($uri, $ip, $start + 1);
                $this -> assertTrue(false);
            } catch (ActorDiscoveryBusy $exception) {
                $elapsed = hrtime(true) / 1e9 - $start;
                $this -> assertTrue(str_contains($exception -> getMessage(), 'failed or timed out'), $exception -> getMessage());
                $this -> assertTrue($elapsed >= 0.75 && $elapsed < 5, 'A stalled child must be killed near the one-second test deadline; elapsed ' . $elapsed);
            }
            try {
                StalledActorDiscovery::resolve($uri, $ip, hrtime(true) / 1e9 + 20);
                $this -> assertTrue(false);
            } catch (ActorDiscoveryBusy $exception) {
                $this -> assertTrue(str_contains($exception -> getMessage(), 'cooling down'));
            }
        });
    }
}
