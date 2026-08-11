<?php

declare(strict_types=1);

/**
 * What the network gets told when something changes here, and what it
 * deliberately is not told.
 */
class FediversePublisherTest extends DatabaseTestCase
{
    private static function memberWithAFollower(): User
    {
        $user = DB::row('
SELECT *
    FROM `Users`
    WHERE `userId` = ?
', 'User', 'i', self::createUser());

        FediverseFollower::add(
            (int) $user -> userId,
            'https://remote.invalid/users/f-' . bin2hex(random_bytes(4)),
            'https://remote.invalid/inbox-' . bin2hex(random_bytes(4)),
            null,
            'https://remote.invalid/activities/x'
        );

        return $user;
    }

    public function testAProfileChangeIsSentToFollowers(): void
    {
        $user = self::memberWithAFollower();
        $before = FediverseDelivery::pendingCount();

        FediversePublisher::profileUpdated($user);

        $this -> assertSame($before + 1, FediverseDelivery::pendingCount());
    }

    public function testADeletedAccountIsAnnouncedBeforeItGoes(): void
    {
        $user = self::memberWithAFollower();
        $before = FediverseDelivery::pendingCount();

        FediversePublisher::accountDeleted($user);

        $this -> assertSame($before + 1, FediverseDelivery::pendingCount());
    }

    public function testNothingIsPublishedForABannedMember(): void
    {
        // Their actor stops resolving, so anything sent for them would be
        // unverifiable at the far end - and publishing for someone this server
        // has stopped hosting is wrong however it is received.
        $user = self::memberWithAFollower();

        DB::run('
UPDATE `Users`
    SET `banned` = 1
    WHERE `userId` = ?
', 'i', (int) $user -> userId);

        $banned = DB::row('
SELECT *
    FROM `Users`
    WHERE `userId` = ?
', 'User', 'i', (int) $user -> userId);

        $before = FediverseDelivery::pendingCount();
        FediverseDelivery::fanOut($banned, ['type' => 'Create']);

        $this -> assertSame($before, FediverseDelivery::pendingCount());
    }

    public function testAModeratorsDeletionGoesOutAsTheAuthor(): void
    {
        // The post is equally gone either way, and the followers holding a copy
        // are the author's - so the withdrawal is theirs to make.
        $user = self::memberWithAFollower();

        DB::run('
INSERT INTO `Posts` (`userId`, `description`, `descriptionDelta`)
    VALUES (?, ?, ?)
', 'iss', (int) $user -> userId, 'text', json_encode([['insert' => "text\n"]]));

        $post_id = (int) mysqli_insert_id(DB::connection());

        $author = FediversePublisher::authorOf($post_id);
        $uri = FediversePublisher::objectURIFor($post_id);

        $this -> assertNotNull($author);
        $this -> assertSame((int) $user -> userId, (int) $author -> userId);
        $this -> assertSame(ServerURL::absolute('/users/' . $user -> slug . '/' . $post_id), $uri);
    }

    public function testAPostFromElsewhereHasNothingForUsToWithdraw(): void
    {
        $user = self::memberWithAFollower();

        DB::run('
INSERT INTO `Posts` (`userId`, `description`, `descriptionDelta`, `remoteObjectURI`)
    VALUES (?, ?, ?, ?)
', 'isss', (int) $user -> userId, 'text', json_encode([['insert' => "text\n"]]), 'https://mastodon.social/x/' . bin2hex(random_bytes(4)));

        $post_id = (int) mysqli_insert_id(DB::connection());

        // Its own server will send its own Delete; ours would be a second one
        // for an object we never published.
        $this -> assertNull(FediversePublisher::objectURIFor($post_id));
    }

    public function testNodeInfoCountsOnlyWhatIsActuallyOurs(): void
    {
        $members_before = NodeInfo::memberCount();
        $posts_before = NodeInfo::localPostCount();

        $user = DB::row('
SELECT *
    FROM `Users`
    WHERE `userId` = ?
', 'User', 'i', self::createUser());

        // A shadow row is a remote account, not a member here; counting them
        // would inflate this server at every other server's expense.
        DB::run('
INSERT INTO `Users` (`slug`, `email`, `passwordHash`, `remoteActorURI`)
    VALUES (?, ?, ?, ?)
', 'ssss', 'test-shadow-' . bin2hex(random_bytes(6)), 'test-' . bin2hex(random_bytes(6)) . '@example.test', self::cheapHash('x'), 'https://remote.invalid/users/' . bin2hex(random_bytes(4)));

        DB::run('
INSERT INTO `Posts` (`userId`, `description`, `descriptionDelta`)
    VALUES (?, ?, ?)
', 'iss', (int) $user -> userId, 'ours', json_encode([['insert' => "ours\n"]]));

        DB::run('
INSERT INTO `Posts` (`userId`, `description`, `descriptionDelta`, `remoteObjectURI`)
    VALUES (?, ?, ?, ?)
', 'isss', (int) $user -> userId, 'theirs', json_encode([['insert' => "theirs\n"]]), 'https://mastodon.social/y/' . bin2hex(random_bytes(4)));

        $this -> assertSame($members_before + 1, NodeInfo::memberCount());
        $this -> assertSame($posts_before + 1, NodeInfo::localPostCount());
    }
}
