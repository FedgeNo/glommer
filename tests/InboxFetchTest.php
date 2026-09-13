<?php

declare(strict_types=1);

class InboxFetchTest extends DatabaseTestCase
{
    private function enqueue(string $actor = 'https://inbox-fetch.test/alice'): array
    {
        $uri = 'https://inbox-fetch.test/notes/' . bin2hex(random_bytes(6));
        $activity = ['id' => $uri . '/create', 'type' => 'Create', 'object' => $uri, 'to' => ['https://local.test/users/bob']];
        InboxFetch::enqueue($activity, $actor, $uri);

        return [$activity, $actor, $uri];
    }

    public function testQueuePreservesSignerAudienceAndDeduplicatesOnlyTheSameDelivery(): void
    {
        DB::run('DELETE FROM `InboxFetches`');
        [$activity, $actor, $uri] = $this -> enqueue();
        InboxFetch::enqueue($activity, $actor, $uri);
        $this -> assertSame(1, InboxFetch::pendingCount());
        $row = InboxFetch::claim();
        $this -> assertSame($actor, $row -> actorURI);
        $this -> assertSame($activity, json_decode($row -> activity, true));
        $this -> assertNull(InboxFetch::claim(), 'another worker cannot claim an active lease');
        InboxFetch::enqueue($activity, 'https://inbox-fetch.test/other', $uri);
        $this -> assertSame(2, InboxFetch::pendingCount());
        DB::run('DELETE FROM `InboxFetches`');
    }

    public function testFailedReadsReleaseTheirLeaseAndBackOff(): void
    {
        DB::run('DELETE FROM `InboxFetches`');
        $this -> enqueue();
        $row = InboxFetch::claim();
        InboxFetch::failed($row -> inboxFetchId, 0);
        $this -> assertNull(InboxFetch::claim());
        $retry = DB::row('SELECT * FROM `InboxFetches` WHERE `inboxFetchId` = ?', 'InboxFetch', 'i', $row -> inboxFetchId);
        $this -> assertSame(1, $retry -> attempts);
        $this -> assertNull($retry -> claimedUntil);
        DB::run('UPDATE `InboxFetches` SET `nextAttemptAt` = NOW() - INTERVAL 1 SECOND');
        $this -> assertSame($row -> inboxFetchId, InboxFetch::claim() -> inboxFetchId);
        InboxFetch::failed($row -> inboxFetchId, InboxFetch::MAX_ATTEMPTS - 1);
        $this -> assertSame(0, InboxFetch::pendingCount());
    }

    public function testExpiredLeaseCanBeReclaimedAndDeleteIsScopedToTheSigner(): void
    {
        DB::run('DELETE FROM `InboxFetches`');
        [, $actor, $uri] = $this -> enqueue();
        $row = InboxFetch::claim();
        DB::run('UPDATE `InboxFetches` SET `claimedUntil` = NOW() - INTERVAL 1 SECOND');
        $this -> assertSame($row -> inboxFetchId, InboxFetch::claim() -> inboxFetchId);
        InboxFetch::cancel('https://inbox-fetch.test/other', $uri);
        $this -> assertSame(1, InboxFetch::pendingCount());
        InboxFetch::cancel($actor, $uri);
        $this -> assertSame(0, InboxFetch::pendingCount());
    }

    public function testFailedDeliveryDoesNotPoisonReplayDetection(): void
    {
        $signature = 'failed-delivery-' . bin2hex(random_bytes(8));
        $this -> assertFalse(ActivityPubReplay::seenBefore($signature));
        ActivityPubReplay::forget($signature);
        $this -> assertFalse(ActivityPubReplay::seenBefore($signature));
        $this -> assertTrue(ActivityPubReplay::seenBefore($signature));
        ActivityPubReplay::forget($signature);
    }

    public function testAFullQueueFailsNewDeliveriesButStillAcceptsDuplicates(): void
    {
        DB::run('DELETE FROM `InboxFetches`');
        [$activity, $actor, $uri] = $this -> enqueue();

        try {
            // 10 x 10 x 10 x 5 rows, generated inside the disposable database.
            $digits = '(SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9)';
            DB::run('INSERT INTO `InboxFetches` (`activityHash`, `actorURI`, `objectURI`, `activity`)
SELECT SHA2(CONCAT(a.n, b.n, c.n, d.n), 256), ?, ?, ?
    FROM ' . $digits . ' a CROSS JOIN ' . $digits . ' b CROSS JOIN ' . $digits . ' c CROSS JOIN ' . $digits . ' d
    WHERE d.n < 5 LIMIT 4999', 'sss', $actor, $uri, '{}');
            $this -> assertSame(InboxFetch::MAX_PENDING, InboxFetch::pendingCount());
            InboxFetch::enqueue($activity, $actor, $uri);
            $failed = false;

            try {
                $this -> enqueue();
            } catch (\RuntimeException $exception) {
                $failed = true;
            }

            $this -> assertTrue($failed, 'a new delivery must fail instead of being acknowledged and lost');
            $this -> assertSame(InboxFetch::MAX_PENDING, InboxFetch::pendingCount());
        } finally {
            DB::run('DELETE FROM `InboxFetches`');
        }
    }
}
