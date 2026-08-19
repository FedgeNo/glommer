<?php

declare(strict_types=1);

class FediverseDeliveryTest extends DatabaseTestCase
{
    private static function delivery(?string $claimed_until = null, ?int $actor_user_id = null): int
    {
        $actor_user_id ??= self::createUser();

        DB::run('
INSERT INTO `FediverseDeliveries` (`actorUserId`, `inboxURL`, `activity`, `nextAttemptAt`, `claimedUntil`)
    VALUES (?, ?, ?, ?, ?)
', 'issss', $actor_user_id, 'https://delivery-' . bin2hex(random_bytes(6)) . '.invalid/inbox', '{"type":"Create"}', '2000-01-01 00:00:00', $claimed_until);

        return (int) mysqli_insert_id(DB::connection());
    }

    /** @return int[] */
    private static function deliveryBatch(?string $claimed_until = null): array
    {
        $actor_user_id = self::createUser();
        $delivery_ids = [];

        foreach (range(1, FediverseDelivery::BATCH_SIZE) as $index) {
            $delivery_ids[] = self::delivery($claimed_until, $actor_user_id);
        }

        return $delivery_ids;
    }

    /** @param int[] $delivery_ids */
    private static function clear(array $delivery_ids): void
    {
        foreach ($delivery_ids as $delivery_id) {
            FediverseDelivery::succeeded($delivery_id);
        }
    }

    private static function row(int $delivery_id): ?FediverseDeliveryData
    {
        return DB::row('
SELECT *
    FROM `FediverseDeliveries`
    WHERE `deliveryId` = ?
', 'FediverseDeliveryData', 'i', $delivery_id);
    }

    private static function contains(array $rows, int $delivery_id): bool
    {
        foreach ($rows as $row) {
            if ((int) $row -> deliveryId === $delivery_id) {
                return true;
            }
        }

        return false;
    }

    public function testAnUnencodableActivityIsNotQueued(): void
    {
        $before = FediverseDelivery::pendingCount();

        FediverseDelivery::enqueue(self::createUser(), ['body' => "\xB1\x31"], ['https://remote.invalid/inbox']);

        $this -> assertSame($before, FediverseDelivery::pendingCount());
    }

    public function testNoDestinationQueuesNothing(): void
    {
        $before = FediverseDelivery::pendingCount();

        FediverseDelivery::enqueue(self::createUser(), ['type' => 'Create'], []);

        $this -> assertSame($before, FediverseDelivery::pendingCount());
    }

    public function testADueDeliveryGetsAnExpiringClaim(): void
    {
        $delivery_ids = self::deliveryBatch();
        $due = FediverseDelivery::due();
        $claimed = self::row($delivery_ids[0]);

        self::clear($delivery_ids);

        foreach ($delivery_ids as $delivery_id) {
            $this -> assertTrue(self::contains($due, $delivery_id));
        }

        $this -> assertNotNull($claimed);
        $this -> assertTrue(strtotime((string) $claimed -> claimedUntil) > time());
    }

    public function testAnExpiredClaimCanBeTakenAgain(): void
    {
        $delivery_ids = self::deliveryBatch('2000-01-01 00:00:00');
        $due = FediverseDelivery::due();
        $claimed = self::row($delivery_ids[0]);

        self::clear($delivery_ids);

        foreach ($delivery_ids as $delivery_id) {
            $this -> assertTrue(self::contains($due, $delivery_id));
        }

        $this -> assertTrue(strtotime((string) $claimed -> claimedUntil) > time());
    }

    public function testAFailureReleasesTheClaimAndKeepsAValidBoundedError(): void
    {
        $delivery_id = self::delivery(gmdate('Y-m-d H:i:s', time() + 600));

        $this -> assertFalse(FediverseDelivery::failed($delivery_id, 0, str_repeat('é', 300)));

        $row = self::row($delivery_id);
        FediverseDelivery::succeeded($delivery_id);

        $this -> assertNotNull($row);
        $this -> assertSame(1, (int) $row -> attempts);
        $this -> assertNull($row -> claimedUntil);
        $this -> assertSame(255, mb_strlen((string) $row -> lastError));
        $this -> assertTrue(mb_check_encoding((string) $row -> lastError, 'UTF-8'));
        $this -> assertTrue(strtotime((string) $row -> nextAttemptAt) > time());
    }

    public function testASuccessfulDeliveryLeavesTheQueue(): void
    {
        $delivery_id = self::delivery();

        FediverseDelivery::succeeded($delivery_id);

        $this -> assertNull(self::row($delivery_id));
    }
}
