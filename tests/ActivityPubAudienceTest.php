<?php

declare(strict_types=1);

/**
 * Who a post is actually sent to. Followers are not the whole audience: a reply
 * is aimed at whoever wrote the thing it answers, and a mention is aimed at the
 * person named. Neither is necessarily a follower, and before this both were
 * silently dropped.
 */
class ActivityPubAudienceTest extends DatabaseTestCase
{
    private static function localUser(): User
    {
        return DB::row('
SELECT *
    FROM `Users`
    WHERE `userId` = ?
', 'User', 'i', self::createUser());
    }

    private static function remoteUser(string $handle): User
    {
        $actor = 'https://' . explode('@', $handle)[1] . '/users/' . explode('@', $handle)[0];

        DB::run('
INSERT INTO `Users` (`slug`, `email`, `passwordHash`, `remoteActorURI`, `remoteActorPublicKeyPem`, `remoteActorInboxURL`, `verified`)
    VALUES (?, ?, ?, ?, ?, ?, ?)
', 'ssssssi', $handle, 'test-' . bin2hex(random_bytes(6)) . '@example.test', password_hash('x', PASSWORD_DEFAULT), $actor, 'key', $actor . '/inbox', 1);

        return DB::row('
SELECT *
    FROM `Users`
    WHERE `userId` = ?
', 'User', 'i', (int) mysqli_insert_id(DB::connection()));
    }

    private static function post(int $user_id, ?int $parent_id = null, ?string $remote_uri = null): Post
    {
        DB::run('
INSERT INTO `Posts` (`userId`, `parentId`, `description`, `descriptionDelta`, `remoteObjectURI`)
    VALUES (?, ?, ?, ?, ?)
', 'iisss', $user_id, $parent_id, 'text', json_encode([['insert' => "text\n"]]), $remote_uri);

        return DB::row('
SELECT *
    FROM `Posts`
    WHERE `postId` = ?
', 'Post', 'i', (int) mysqli_insert_id(DB::connection()));
    }

    public function testAReplyIsAddressedToTheAuthorItAnswers(): void
    {
        $them = self::remoteUser('someone' . bin2hex(random_bytes(3)) . '@remote.invalid');
        $us = self::localUser();

        $theirs = self::post((int) $them -> userId, null, 'https://remote.invalid/statuses/' . bin2hex(random_bytes(4)));
        $reply = self::post((int) $us -> userId, (int) $theirs -> postId);

        $recipients = ActivityPubNote::remoteRecipients($reply);

        $this -> assertTrue(isset($recipients[(string) $them -> remoteActorURI]), 'the person replied to should be a recipient');

        $document = ActivityPubNote::document($reply, $us);
        $this -> assertTrue(in_array((string) $them -> remoteActorURI, $document['to'], true));
    }

    public function testAReplyToALocalPostAddsNoRemoteRecipient(): void
    {
        // They are already reading it here; nothing needs sending anywhere.
        $us = self::localUser();
        $parent = self::post((int) $us -> userId);
        $reply = self::post((int) $us -> userId, (int) $parent -> postId);

        $this -> assertSame([], ActivityPubNote::remoteRecipients($reply));
    }

    public function testAMentionedRemoteAccountIsAddressedAndTagged(): void
    {
        $handle = 'named' . bin2hex(random_bytes(3)) . '@remote.invalid';
        $them = self::remoteUser($handle);
        $us = self::localUser();
        $post = self::post((int) $us -> userId);

        DB::run('
INSERT INTO `PostMentions` (`postId`, `userId`)
    VALUES (?, ?)
', 'ii', (int) $post -> postId, (int) $them -> userId);

        $document = ActivityPubNote::document($post, $us);

        $this -> assertTrue(in_array((string) $them -> remoteActorURI, $document['to'], true), 'a mentioned account should be addressed');

        $mentions = array_filter($document['tag'] ?? [], static fn (array $tag): bool => $tag['type'] === 'Mention');
        $names = array_map(static fn (array $tag): string => $tag['name'], $mentions);

        $this -> assertTrue(in_array('@' . $handle, $names, true), 'a mentioned account should be tagged so it renders as a link');
    }

    public function testAMentionedLocalMemberIsNotSentAnything(): void
    {
        $us = self::localUser();
        $friend = self::localUser();
        $post = self::post((int) $us -> userId);

        DB::run('
INSERT INTO `PostMentions` (`postId`, `userId`)
    VALUES (?, ?)
', 'ii', (int) $post -> postId, (int) $friend -> userId);

        $this -> assertSame([], ActivityPubNote::remoteRecipients($post));
    }

    public function testTheSamePersonNamedTwiceIsOneRecipient(): void
    {
        $them = self::remoteUser('twice' . bin2hex(random_bytes(3)) . '@remote.invalid');
        $us = self::localUser();

        $theirs = self::post((int) $them -> userId, null, 'https://remote.invalid/statuses/' . bin2hex(random_bytes(4)));
        $reply = self::post((int) $us -> userId, (int) $theirs -> postId);

        // Replied to AND mentioned - still one person, one delivery.
        DB::run('
INSERT INTO `PostMentions` (`postId`, `userId`)
    VALUES (?, ?)
', 'ii', (int) $reply -> postId, (int) $them -> userId);

        $this -> assertSame(1, count(ActivityPubNote::remoteRecipients($reply)));
    }

    public function testAReplyReachesTheAuthorEvenWithNoFollowers(): void
    {
        // The whole point: before this, a reply to a Mastodon post went to the
        // author's followers and therefore, for someone with none, nowhere.
        $them = self::remoteUser('lonely' . bin2hex(random_bytes(3)) . '@remote.invalid');
        $us = self::localUser();

        $theirs = self::post((int) $them -> userId, null, 'https://remote.invalid/statuses/' . bin2hex(random_bytes(4)));
        $reply = self::post((int) $us -> userId, (int) $theirs -> postId);

        $before = FediverseDelivery::pendingCount();
        FediversePublisher::published($reply, $us);

        $this -> assertSame($before + 1, FediverseDelivery::pendingCount());
    }
}
