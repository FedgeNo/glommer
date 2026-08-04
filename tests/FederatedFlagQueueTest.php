<?php

declare(strict_types=1);

/**
 * A report about a post on another server has to reach that server's own
 * moderators, and it must not name whoever reported - so it is signed by the
 * instance rather than by a member. That is why it is queued with no member
 * against it: the queue is what retries, and a report to a server that
 * happened to be down would otherwise simply be lost.
 */
class FederatedFlagQueueTest extends DatabaseTestCase
{
    private function queued(): array
    {
        return DB::rows('
SELECT `deliveryId`, `actorUserId`, `inboxURL`, `activity`
    FROM `FediverseDeliveries`
    ORDER BY `deliveryId` DESC
', 'FediverseDeliveryData');
    }

    public function testTheQueueAcceptsADeliveryWithNoMemberBehindIt(): void
    {
        FediverseDelivery::enqueue(null, ['type' => 'Flag'], ['https://remote.example/inbox']);

        $queued = $this -> queued();

        $this -> assertTrue($queued !== [], 'the delivery should have been queued');
        $this -> assertNull($queued[0] -> actorUserId);
    }

    public function testAMemberSignedDeliveryStillNamesThem(): void
    {
        $user_id = self::createUser();

        FediverseDelivery::enqueue($user_id, ['type' => 'Create'], ['https://remote.example/inbox']);

        $this -> assertSame($user_id, (int) $this -> queued()[0] -> actorUserId);
    }
}
