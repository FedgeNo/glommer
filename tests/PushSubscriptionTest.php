<?php

declare(strict_types=1);

class PushSubscriptionTest extends DatabaseTestCase
{
    private function subscribe(int $id, string $endpoint): string
    {
        return PushSubscription::subscribe($id, $endpoint, 'test public key', 'test auth', 'Firefox/100 Linux');
    }

    public function testAccountCapPreservesExistingSubscriptionsAndAllowsRefresh(): void
    {
        $user = self::createUser();
        $prefix = 'https://push.invalid/' . bin2hex(random_bytes(8)) . '/';
        try {
            for ($i = 0; $i < 10; $i++) $this -> assertSame('subscribed', $this -> subscribe($user, $prefix . $i));
            $this -> assertSame('full', $this -> subscribe($user, $prefix . 'extra'));
            $this -> assertSame('subscribed', $this -> subscribe($user, $prefix . '0'));
            $this -> assertCount(10, PushSubscription::listing($user));
            $this -> assertCount(10, DB::rows('SELECT `registrationId` FROM `PushRegistrations` WHERE `userId` = ?', \stdClass::class, 'i', $user));
            $status = PushSubscription::status($user, $prefix . '0');
            $this -> assertTrue($status['subscribed']);
            $this -> assertTrue($status['subscriptions'][0]['current']);
            $this -> assertFalse(str_contains(json_encode($status), $prefix));
            $this -> assertFalse(str_contains(json_encode($status), 'test auth'));
        } finally {
            DB::run('DELETE FROM `Users` WHERE `userId` = ?', 'i', $user);
        }
    }

    public function testRemovingSubscriptionsDoesNotResetTheRollingDailyAllowance(): void
    {
        $user = self::createUser();
        $endpoint = 'https://push.invalid/' . bin2hex(random_bytes(8));
        try {
            for ($i = 0; $i < 10; $i++) {
                $this -> assertSame('subscribed', $this -> subscribe($user, $endpoint));
                PushSubscription::remove($user, null, $endpoint);
            }
            $this -> assertSame('limited', $this -> subscribe($user, $endpoint));
            DB::run('UPDATE `PushRegistrations` SET `createdAt` = NOW() - INTERVAL 25 HOUR WHERE `userId` = ?', 'i', $user);
            $this -> assertSame('subscribed', $this -> subscribe($user, $endpoint));
        } finally {
            DB::run('DELETE FROM `Users` WHERE `userId` = ?', 'i', $user);
        }
    }

    public function testRemovalIsScopedAndSharedBrowserTransferDropsOldQueuedNotifications(): void
    {
        $first = self::createUser();
        $second = self::createUser();
        $endpoint = 'https://push.invalid/' . bin2hex(random_bytes(8));
        try {
            $this -> subscribe($first, $endpoint);
            $id = PushSubscription::listing($first)[0]['id'];
            PushSubscription::remove($second, $id);
            $this -> assertCount(1, PushSubscription::listing($first));
            DB::run('INSERT INTO `PushDeliveries` (`pushSubscriptionId`, `payload`) VALUES (?, ?)', 'is', $id, 'private notification');
            $this -> assertSame('subscribed', $this -> subscribe($second, $endpoint));
            $this -> assertCount(0, PushSubscription::listing($first));
            $this -> assertCount(1, PushSubscription::listing($second));
            $this -> assertCount(0, DB::rows('SELECT `pushDeliveryId` FROM `PushDeliveries` WHERE `pushSubscriptionId` = ?', \stdClass::class, 'i', $id));
        } finally {
            DB::run('DELETE FROM `Users` WHERE `userId` IN (?, ?)', 'ii', $first, $second);
        }
    }

    public function testIssuanceFailureCannotLeaveAnUncountedSubscription(): void
    {
        $user = self::createUser();
        mysqli_query(DB::connection(), "CREATE TRIGGER FailPushRegistration BEFORE INSERT ON PushRegistrations FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'forced push issuance failure'");
        try {
            try {
                $this -> subscribe($user, 'https://push.invalid/' . bin2hex(random_bytes(8)));
                $this -> assertTrue(false);
            } catch (\mysqli_sql_exception $exception) {
                $this -> assertTrue(str_contains($exception -> getMessage(), 'forced push issuance failure'));
            }
            $this -> assertCount(0, PushSubscription::listing($user));
        } finally {
            mysqli_query(DB::connection(), 'DROP TRIGGER FailPushRegistration');
            DB::run('DELETE FROM `Users` WHERE `userId` = ?', 'i', $user);
        }
    }

    public function testConcurrentRegistrationCannotExceedEitherLimit(): void
    {
        foreach (['full', 'limited'] as $refusal) {
            $user = self::createUser();
            $prefix = 'https://push.invalid/' . bin2hex(random_bytes(8)) . '/';
            $children = [];
            try {
                for ($i = 0; $i < 9; $i++) {
                    $this -> subscribe($user, $prefix . $i);
                    if ($refusal === 'limited') PushSubscription::remove($user, null, $prefix . $i);
                }
                DB::transaction(function () use ($user, $prefix, &$children): void {
                    User::loadForUpdate($user);
                    for ($i = 0; $i < 2; $i++) {
                        $code = 'spl_autoload_register(static function ($class) { require getcwd() . "/src/classes/" . $class . ".php"; });'
                            . 'require "src/functions.php"; echo "ready\\n"; flush();'
                            . 'echo PushSubscription::subscribe(' . $user . ', ' . var_export($prefix . 'parallel-' . $i, true)
                            . ', "key", "auth", null), "\\n";';
                        $process = proc_open(['timeout', '5', PHP_BINARY, '-r', $code],
                            [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                        $children[] = [$process, $pipes];
                        $this -> assertSame("ready\n", fgets($pipes[1]));
                    }
                });
                $statuses = [];
                foreach ($children as [$process, $pipes]) {
                    $statuses[] = trim(stream_get_contents($pipes[1]));
                }
                sort($statuses);
                $this -> assertSame([$refusal, 'subscribed'], $statuses);
                $this -> assertCount(10, DB::rows('SELECT `registrationId` FROM `PushRegistrations` WHERE `userId` = ?', \stdClass::class, 'i', $user));
            } finally {
                foreach ($children as [$process, $pipes]) {
                    foreach ($pipes as $pipe) fclose($pipe);
                    proc_close($process);
                }
                DB::run('DELETE FROM `Users` WHERE `userId` = ?', 'i', $user);
            }
        }
    }
}
