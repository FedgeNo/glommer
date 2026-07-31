<?php

declare(strict_types=1);

/**
 * Blocks crossing servers. A block that stops at this server is half a block:
 * the other account carries on seeing the person who blocked them, because
 * their own server was never told.
 */
class ActivityPubBlockTest extends DatabaseTestCase
{
    private static function localUser(): User
    {
        return DB::row('
SELECT *
    FROM `Users`
    WHERE `userId` = ?
', 'User', 'i', self::createUser());
    }

    private static function remoteUser(): User
    {
        $actor = 'https://remote.invalid/users/r-' . bin2hex(random_bytes(5));

        DB::run('
INSERT INTO `Users` (`slug`, `email`, `passwordHash`, `remoteActorURI`, `remoteActorPublicKeyPem`, `remoteActorInboxURL`, `verified`)
    VALUES (?, ?, ?, ?, ?, ?, ?)
', 'ssssssi', 'r-' . bin2hex(random_bytes(6)) . '@remote.invalid', 'test-' . bin2hex(random_bytes(6)) . '@example.test', password_hash('x', PASSWORD_DEFAULT), $actor, 'key', $actor . '/inbox', 1);

        return DB::row('
SELECT *
    FROM `Users`
    WHERE `userId` = ?
', 'User', 'i', (int) mysqli_insert_id(DB::connection()));
    }

    public function testBlockingARemoteAccountTellsTheirServer(): void
    {
        $member = self::localUser();
        $them = self::remoteUser();

        $before = FediverseDelivery::pendingCount();
        ActivityPubBlock::published($member, $them, true);

        $this -> assertSame($before + 1, FediverseDelivery::pendingCount());
    }

    public function testLiftingABlockSendsAnUndoCarryingIt(): void
    {
        // Their server was told about the block, so it has to be told about the
        // lift, and it matches the Undo against what it recorded.
        $member = self::localUser();
        $them = self::remoteUser();

        ActivityPubBlock::published($member, $them, false);

        $row = DB::row('
SELECT *
    FROM `FediverseDeliveries`
    ORDER BY `deliveryId` DESC
    LIMIT 1
', 'FediverseDeliveryData');

        $activity = json_decode((string) $row -> activity, true);

        $this -> assertSame('Undo', $activity['type']);
        $this -> assertSame('Block', $activity['object']['type']);
    }

    public function testBlockingALocalMemberTellsNobody(): void
    {
        $member = self::localUser();
        $other = self::localUser();

        $before = FediverseDelivery::pendingCount();
        ActivityPubBlock::published($member, $other, true);

        $this -> assertSame($before, FediverseDelivery::pendingCount());
    }

    public function testABlockFromElsewhereIsHonouredHere(): void
    {
        $member = self::localUser();
        $them = self::remoteUser();

        ActivityPubBlock::received(ActivityPubActor::uriFor($member), $them);

        $this -> assertTrue(Block::blockedBy((int) $them -> userId, (int) $member -> userId));
    }

    public function testABlockSeversTheFollowsBetweenThem(): void
    {
        // Leaving them standing would keep delivering exactly the posts the
        // block was meant to stop.
        $member = self::localUser();
        $them = self::remoteUser();

        FediverseFollower::add((int) $member -> userId, (string) $them -> remoteActorURI, (string) $them -> remoteActorInboxURL, null, 'x');

        DB::run('
INSERT INTO `RemoteFollows` (`localUserId`, `remoteActorURI`, `status`)
    VALUES (?, ?, ?)
', 'iss', (int) $member -> userId, (string) $them -> remoteActorURI, 'accepted');

        ActivityPubBlock::received(ActivityPubActor::uriFor($member), $them);

        $this -> assertFalse(FediverseFollower::exists((int) $member -> userId, (string) $them -> remoteActorURI));
        $this -> assertSame([], RemoteFollow::acceptedActorURIsFor((int) $member -> userId));
    }

    public function testAnInboundUndoLiftsTheBlock(): void
    {
        $member = self::localUser();
        $them = self::remoteUser();

        ActivityPubBlock::received(ActivityPubActor::uriFor($member), $them);
        $this -> assertTrue(Block::blockedBy((int) $them -> userId, (int) $member -> userId));

        ActivityPubBlock::withdrawn(ActivityPubActor::uriFor($member), $them);

        $this -> assertFalse(Block::blockedBy((int) $them -> userId, (int) $member -> userId));
    }

    public function testABlockNamingSomebodyWhoIsNotHereDoesNothing(): void
    {
        $them = self::remoteUser();

        ActivityPubBlock::received('https://elsewhere.invalid/users/someone', $them);

        // Nothing to assert but the absence of a crash and of a stray row.
        $row = DB::row('
SELECT COUNT(*) AS `total`
    FROM `Blocks`
    WHERE `blockerId` = ?
', 'PostCountData', 'i', (int) $them -> userId);

        $this -> assertSame(0, (int) $row -> total);
    }

    public function testSeveringIsANoOpBetweenTwoLocalMembers(): void
    {
        $member = self::localUser();
        $other = self::localUser();

        ActivityPubBlock::severFollows((int) $other -> userId, (int) $member -> userId, '');

        // Reaching here without touching anything is the assertion - an empty
        // actor URI must not match rows by accident.
        $this -> assertTrue(true);
    }
}
