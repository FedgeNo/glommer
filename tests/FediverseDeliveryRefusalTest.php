<?php

declare(strict_types=1);

/**
 * Keeping what a server said when it turned an activity away.
 *
 * The failure this exists to end is a silent one: a delivery that was rejected
 * and a delivery that arrived both come back as the same bool, both delete
 * their queue row, and neither leaves anything to read. An interop question
 * then has no answer except to guess, which is how an outbound poll vote went
 * missing without anybody being able to say why.
 */
class FediverseDeliveryRefusalTest extends DatabaseTestCase
{
    private static function countRefusals(): int
    {
        $row = DB::row('
SELECT COUNT(*) AS `total`
    FROM `FediverseDeliveryRefusals`
', 'PostCountData');

        return $row === null ? 0 : (int) $row -> total;
    }

    private static function newest(): ?FediverseDeliveryRefusal
    {
        return FediverseDeliveryRefusal::recent(1)[0] ?? null;
    }

    public function testARefusalKeepsWhatTheServerSaid(): void
    {
        FediverseDeliveryRefusal::record(
            'https://remote.test/inbox',
            ['type' => 'Create', 'id' => 'https://glommer.test/users/x#poll-votes/1/create'],
            422,
            'Unprocessable entity: poll is expired'
        );

        $refusal = self::newest();

        $this -> assertNotNull($refusal);
        $this -> assertSame(422, (int) $refusal -> status);
        $this -> assertSame('Create', $refusal -> activityType);
        $this -> assertSame('https://glommer.test/users/x#poll-votes/1/create', $refusal -> activityURI);
        $this -> assertTrue(str_contains((string) $refusal -> body, 'poll is expired'));
    }

    /**
     * A request that never reached HTTP is a different failure from being
     * turned away, and saying so is the point of keeping the status nullable.
     */
    public function testAConnectionThatNeverReachedHTTPIsRecordedWithNoStatus(): void
    {
        FediverseDeliveryRefusal::record('https://gone.test/inbox', ['type' => 'Like'], null, null);

        $refusal = self::newest();

        $this -> assertNotNull($refusal);
        $this -> assertNull($refusal -> status);
        $this -> assertNull($refusal -> body);
    }

    /** An oversized body is cut rather than refused by the column. */
    public function testAnOversizedBodyIsCutToFit(): void
    {
        FediverseDeliveryRefusal::record('https://remote.test/inbox', ['type' => 'Create'], 500, str_repeat('x', 4000));

        $this -> assertTrue(mb_strlen((string) self::newest() ?-> body) <= 500);
    }

    /** An activity with no id still records - the reason is the point, not the name. */
    public function testAnActivityWithNoIdStillRecords(): void
    {
        $before = self::countRefusals();

        FediverseDeliveryRefusal::record('https://remote.test/inbox', [], 401, 'Request not signed');

        $this -> assertSame($before + 1, self::countRefusals());
        $this -> assertNull(self::newest() ?-> activityType);
    }

    /** A diagnostic log that grew forever would be a slow leak. */
    public function testWhatIsTooOldToTellAnybodyAnythingIsDropped(): void
    {
        FediverseDeliveryRefusal::record('https://old.test/inbox', ['type' => 'Create'], 500, 'ancient');

        DB::run('
UPDATE `FediverseDeliveryRefusals`
    SET `createdAt` = NOW() - INTERVAL ? DAY
    WHERE `inboxURL` = ?
', 'is', FediverseDeliveryRefusal::KEEP_DAYS + 1, 'https://old.test/inbox');

        FediverseDeliveryRefusal::prune();

        $left = DB::row('
SELECT COUNT(*) AS `total`
    FROM `FediverseDeliveryRefusals`
    WHERE `inboxURL` = ?
', 'PostCountData', 's', 'https://old.test/inbox');

        $this -> assertSame(0, (int) $left -> total);
    }
}
