<?php

declare(strict_types=1);

/**
 * Where an inbound reply gets filed.
 *
 * The trap here is that a reply names its parent by URI, and the parent is
 * usually one of ours - so a resolver that only knows how to look up
 * remoteObjectURI finds nothing, and the commonest reply on the network (a
 * stranger answering a member here) is the one case that silently vanishes.
 * Nothing about it looks like a failure: no error, no row, and the thread-fetch
 * queue swallows it and gives up quietly some time later.
 */
class ActivityPubThreadTest extends DatabaseTestCase
{
    private static function localUser(): User
    {
        return DB::row('
SELECT *
    FROM `Users`
    WHERE `userId` = ?
', 'User', 'i', self::createUser());
    }

    private static function shadowUser(string $actor_uri): User
    {
        DB::run('
INSERT INTO `Users` (`slug`, `email`, `passwordHash`, `title`, `remoteActorURI`, `remoteActorPublicKeyPem`, `remoteActorInboxURL`, `verified`)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
', 'sssssssi',
            'test-remote-' . bin2hex(random_bytes(6)),
            'test-' . bin2hex(random_bytes(6)) . '@example.test',
            password_hash('x', PASSWORD_DEFAULT),
            'Remote Person',
            $actor_uri,
            'not-a-real-key',
            $actor_uri . '/inbox',
            1
        );

        return DB::row('
SELECT *
    FROM `Users`
    WHERE `userId` = ?
', 'User', 'i', (int) mysqli_insert_id(DB::connection()));
    }

    private static function post(int $user_id, ?string $remote_uri = null): int
    {
        DB::run('
INSERT INTO `Posts` (`userId`, `description`, `descriptionDelta`, `remoteObjectURI`)
    VALUES (?, ?, ?, ?)
', 'isss', $user_id, 'something worth answering', json_encode([['insert' => "something worth answering\n"]]), $remote_uri);

        return (int) mysqli_insert_id(DB::connection());
    }

    /** Delivers a reply from $actor_uri answering $in_reply_to, and hands back its object URI. */
    private static function deliverReply(string $actor_uri, string $in_reply_to): string
    {
        $object_uri = 'https://remote.test/notes/' . bin2hex(random_bytes(6));

        ActivityPubInbox::process([
            'type' => 'Create',
            'object' => [
                'type' => 'Note',
                'id' => $object_uri,
                'attributedTo' => $actor_uri,
                'content' => 'answering that',
                'inReplyTo' => $in_reply_to,
                'to' => [ActivityPubActor::PUBLIC_AUDIENCE],
            ],
        ], $actor_uri);

        return $object_uri;
    }

    private static function stored(string $object_uri): ?Post
    {
        return DB::row('
SELECT `postId`, `parentId`
    FROM `Posts`
    WHERE `remoteObjectURI` = ?
', 'Post', 's', $object_uri);
    }

    /**
     * Somebody out on the Fediverse answering a post of ours. Their reply names
     * our permalink, which is not a remoteObjectURI and never will be.
     */
    public function testAReplyToOneOfOurOwnPostsIsFiledUnderIt(): void
    {
        $author = self::localUser();
        $post_id = self::post((int) $author -> userId);

        $actor_uri = 'https://remote.test/users/' . bin2hex(random_bytes(6));
        self::shadowUser($actor_uri);

        $reply_uri = self::deliverReply(
            $actor_uri,
            ServerURL::absolute('/users/' . $author -> slug . '/' . $post_id)
        );

        $reply = self::stored($reply_uri);

        $this -> assertNotNull($reply, 'the reply was stored rather than dropped');
        $this -> assertSame($post_id, (int) $reply -> parentId);
    }

    /** The same reply, and nothing of it left waiting to be fetched. */
    public function testAReplyToOneOfOurOwnPostsIsNotQueuedAsAThreadToRead(): void
    {
        $author = self::localUser();
        $post_id = self::post((int) $author -> userId);

        $actor_uri = 'https://remote.test/users/' . bin2hex(random_bytes(6));
        self::shadowUser($actor_uri);

        $reply_uri = self::deliverReply(
            $actor_uri,
            ServerURL::absolute('/users/' . $author -> slug . '/' . $post_id)
        );

        $queued = DB::row('
SELECT `relayFetchId`
    FROM `RelayFetches`
    WHERE `objectURI` = ?
', 'RelayFetch', 's', $reply_uri);

        $this -> assertNull($queued, 'a parent already in hand needs no round trip to read');
    }

    /** A reply to a remote post already held still lands on it - the old path. */
    public function testAReplyToARemotePostWeAlreadyHoldIsFiledUnderIt(): void
    {
        $actor_uri = 'https://remote.test/users/' . bin2hex(random_bytes(6));
        $author = self::shadowUser($actor_uri);

        $parent_uri = 'https://remote.test/notes/' . bin2hex(random_bytes(6));
        $parent_id = self::post((int) $author -> userId, $parent_uri);

        $reply = self::stored(self::deliverReply($actor_uri, $parent_uri));

        $this -> assertNotNull($reply);
        $this -> assertSame($parent_id, (int) $reply -> parentId);
    }

    /**
     * A reply to something nobody here has ever seen is still queued to be read
     * rather than stored parentless - half a conversation is worse than none.
     */
    public function testAReplyToAnUnknownPostIsQueuedToBeRead(): void
    {
        $actor_uri = 'https://remote.test/users/' . bin2hex(random_bytes(6));
        self::shadowUser($actor_uri);

        $reply_uri = self::deliverReply($actor_uri, 'https://remote.test/notes/never-seen-' . bin2hex(random_bytes(6)));

        $this -> assertNull(self::stored($reply_uri), 'nothing is filed until its parent is');

        $queued = DB::row('
SELECT `relayFetchId`
    FROM `RelayFetches`
    WHERE `objectURI` = ?
', 'RelayFetch', 's', $reply_uri);

        $this -> assertNotNull($queued);
    }

    /**
     * A URI on our own host that is not a post of ours resolves to nothing
     * rather than to whatever number happens to be on the end of it.
     */
    public function testAnOurHostURIThatIsNotAPostResolvesToNothing(): void
    {
        $actor_uri = 'https://remote.test/users/' . bin2hex(random_bytes(6));
        self::shadowUser($actor_uri);

        $reply_uri = self::deliverReply($actor_uri, ServerURL::absolute('/topics/hashtag/news'));

        $this -> assertNull(self::stored($reply_uri));
    }
}
