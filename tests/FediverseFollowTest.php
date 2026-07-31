<?php

declare(strict_types=1);

/**
 * Inbound follows and the outbound delivery queue: who is allowed to follow
 * whom, who is allowed to undo it, and what the queue does with a server that
 * will not answer.
 *
 * Deliveries in these tests are aimed at a .invalid host, which cannot resolve
 * by definition (RFC 2606) - the Accept attempt fails fast rather than reaching
 * anything real.
 */
class FediverseFollowTest extends DatabaseTestCase
{
    private static function localUser(): User
    {
        return DB::row('
SELECT *
    FROM `Users`
    WHERE `userId` = ?
', 'User', 'i', self::createUser());
    }

    private static function remoteFollower(string $actor_uri): int
    {
        DB::run('
INSERT INTO `Users` (`slug`, `email`, `passwordHash`, `title`, `remoteActorURI`, `remoteActorPublicKeyPem`, `remoteActorInboxURL`, `verified`)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
', 'sssssssi',
            'test-remote-' . bin2hex(random_bytes(6)),
            'test-' . bin2hex(random_bytes(6)) . '@example.test',
            password_hash('x', PASSWORD_DEFAULT),
            'Remote Follower',
            $actor_uri,
            'not-a-real-key',
            $actor_uri . '/inbox',
            1
        );

        return (int) mysqli_insert_id(DB::connection());
    }

    private static function followActivity(string $actor_uri, string $target_uri): array
    {
        return [
            'id' => 'https://remote.invalid/activities/' . bin2hex(random_bytes(6)),
            'type' => 'Follow',
            'actor' => $actor_uri,
            'object' => $target_uri,
        ];
    }

    public function testSomeoneFollowingAMemberIsRecorded(): void
    {
        $target = self::localUser();
        $actor = 'https://remote.invalid/users/follower-' . bin2hex(random_bytes(4));
        self::remoteFollower($actor);

        ActivityPubInbox::process(self::followActivity($actor, ActivityPubActor::uriFor($target)), $actor);

        $this -> assertTrue(FediverseFollower::exists((int) $target -> userId, $actor));
    }

    public function testAFollowOfSomebodyWhoIsNotHereIsIgnored(): void
    {
        $actor = 'https://remote.invalid/users/follower-' . bin2hex(random_bytes(4));
        self::remoteFollower($actor);

        ActivityPubInbox::process(self::followActivity($actor, 'https://mastodon.social/users/elsewhere'), $actor);

        // Nothing to assert against a row that should not exist except that the
        // whole table stayed empty of it.
        $rows = DB::rows('
SELECT `remoteActorURI`
    FROM `FediverseFollowers`
    WHERE `remoteActorURI` = ?
', 'FediverseFollowerData', 's', $actor);

        $this -> assertSame(0, count($rows));
    }

    public function testAnUndoStopsTheFollow(): void
    {
        $target = self::localUser();
        $actor = 'https://remote.invalid/users/follower-' . bin2hex(random_bytes(4));
        self::remoteFollower($actor);
        $follow = self::followActivity($actor, ActivityPubActor::uriFor($target));

        ActivityPubInbox::process($follow, $actor);
        $this -> assertTrue(FediverseFollower::exists((int) $target -> userId, $actor));

        ActivityPubInbox::process([
            'id' => 'https://remote.invalid/activities/' . bin2hex(random_bytes(6)),
            'type' => 'Undo',
            'actor' => $actor,
            'object' => $follow,
        ], $actor);

        $this -> assertFalse(FediverseFollower::exists((int) $target -> userId, $actor));
    }

    public function testNobodyCanUndoSomeoneElsesFollow(): void
    {
        // The activity says whose follow it is; the signature says who is
        // actually talking. Only the second one may decide.
        $target = self::localUser();
        $victim = 'https://remote.invalid/users/victim-' . bin2hex(random_bytes(4));
        $attacker = 'https://remote.invalid/users/attacker-' . bin2hex(random_bytes(4));
        self::remoteFollower($victim);
        self::remoteFollower($attacker);

        $follow = self::followActivity($victim, ActivityPubActor::uriFor($target));
        ActivityPubInbox::process($follow, $victim);

        // Signed by the attacker, but naming the victim's follow inside.
        ActivityPubInbox::process([
            'id' => 'https://remote.invalid/activities/' . bin2hex(random_bytes(6)),
            'type' => 'Undo',
            'actor' => $victim,
            'object' => $follow,
        ], $attacker);

        $this -> assertTrue(FediverseFollower::exists((int) $target -> userId, $victim), 'the victim should still be following');
    }

    public function testAnActorURIOfOursResolvesToItsMember(): void
    {
        $user = self::localUser();

        $resolved = ActivityPubActor::localUserFromURI(ActivityPubActor::uriFor($user));

        $this -> assertNotNull($resolved);
        $this -> assertSame((int) $user -> userId, (int) $resolved -> userId);
    }

    public function testAnActorURIFromAnotherServerResolvesToNothing(): void
    {
        $this -> assertNull(ActivityPubActor::localUserFromURI('https://mastodon.social/users/someone'));
    }

    public function testAPathThatIsNotAnActorResolvesToNothing(): void
    {
        $host = ActivityPubActor::canonicalHost();

        $this -> assertNull(ActivityPubActor::localUserFromURI('https://' . $host . '/users/nobody-here/'));
        $this -> assertNull(ActivityPubActor::localUserFromURI('https://' . $host . '/about'));
    }

    public function testQueueingWritesOneRowPerInboxAndNoDuplicates(): void
    {
        $user = self::localUser();
        $before = FediverseDelivery::pendingCount();

        FediverseDelivery::enqueue((int) $user -> userId, ['type' => 'Create'], [
            'https://a.invalid/inbox',
            'https://b.invalid/inbox',
            'https://a.invalid/inbox',
        ]);

        $this -> assertSame($before + 2, FediverseDelivery::pendingCount());
    }

    public function testAFailedDeliveryIsRetriedLaterNotImmediately(): void
    {
        $user = self::localUser();
        FediverseDelivery::enqueue((int) $user -> userId, ['type' => 'Create'], ['https://slow.invalid/inbox']);

        $row = DB::row('
SELECT *
    FROM `FediverseDeliveries`
    WHERE `inboxURL` = ?
', 'FediverseDeliveryData', 's', 'https://slow.invalid/inbox');

        FediverseDelivery::failed((int) $row -> deliveryId, 0, 'refused');

        $after = DB::row('
SELECT *
    FROM `FediverseDeliveries`
    WHERE `deliveryId` = ?
', 'FediverseDeliveryData', 'i', (int) $row -> deliveryId);

        $this -> assertNotNull($after, 'a first failure should be retried, not dropped');
        $this -> assertSame(1, (int) $after -> attempts);
        $this -> assertTrue(strtotime((string) $after -> nextAttemptAt) > time(), 'the retry should be in the future');
    }

    public function testAQueueThatNeverDrainsEventuallyGivesUp(): void
    {
        // A server that has refused a dozen times over several days is not
        // going to accept this one activity, and a queue that never gives up
        // becomes a queue that never drains.
        $user = self::localUser();
        FediverseDelivery::enqueue((int) $user -> userId, ['type' => 'Create'], ['https://gone.invalid/inbox']);

        $row = DB::row('
SELECT *
    FROM `FediverseDeliveries`
    WHERE `inboxURL` = ?
', 'FediverseDeliveryData', 's', 'https://gone.invalid/inbox');

        FediverseDelivery::failed((int) $row -> deliveryId, FediverseDelivery::MAX_ATTEMPTS - 1, 'gone');

        $after = DB::row('
SELECT *
    FROM `FediverseDeliveries`
    WHERE `deliveryId` = ?
', 'FediverseDeliveryData', 'i', (int) $row -> deliveryId);

        $this -> assertNull($after);
    }

    public function testAFanOutNeverQueuesForOurOwnServer(): void
    {
        // A member here is read directly. A federated copy on top would be the
        // same post twice.
        $user = self::localUser();
        $host = ActivityPubActor::canonicalHost();

        FediverseFollower::add((int) $user -> userId, 'https://' . $host . '/users/someone/', 'https://' . $host . '/users/someone/inbox', null, 'x');

        $before = FediverseDelivery::pendingCount();
        FediverseDelivery::fanOut($user, ['type' => 'Create']);

        $this -> assertSame($before, FediverseDelivery::pendingCount());
    }

    public function testAFanOutQueuesForRealFollowers(): void
    {
        $user = self::localUser();
        FediverseFollower::add((int) $user -> userId, 'https://real.invalid/users/x', 'https://real.invalid/users/x/inbox', null, 'x');

        $before = FediverseDelivery::pendingCount();
        FediverseDelivery::fanOut($user, ['type' => 'Create']);

        $this -> assertSame($before + 1, FediverseDelivery::pendingCount());
    }
}
