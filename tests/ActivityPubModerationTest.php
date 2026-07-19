<?php

declare(strict_types=1);

/**
 * Exercises the actual moderation/redelivery-safety guarantees against a
 * real database: a tombstoned or already-ingested object never gets
 * re-created, a banned shadow account's deliveries are refused, an
 * unresolvable reply is dropped, and a remote post never leaks into a public
 * feed - only the accepted follower's own Timelines row.
 */
class ActivityPubModerationTest extends DatabaseTestCase
{
    private static function createShadowUser(string $actor_uri, int $banned = 0): int
    {
        DB::run('
INSERT INTO `Users` (`slug`, `email`, `passwordHash`, `title`, `remoteActorURI`, `banned`, `verified`)
    VALUES (?, ?, ?, ?, ?, ?, ?)
', 'sssssii', 'test-shadow-' . bin2hex(random_bytes(6)), 'test-' . bin2hex(random_bytes(6)) . '@example.test', password_hash('x', PASSWORD_DEFAULT), 'Test Shadow', $actor_uri, $banned, 1);

        return (int) mysqli_insert_id(DB::connection());
    }

    private static function acceptFollow(int $local_user_id, string $actor_uri): void
    {
        DB::run('
INSERT INTO `RemoteFollows` (`localUserId`, `remoteActorURI`, `status`)
    VALUES (?, ?, ?)
', 'iss', $local_user_id, $actor_uri, 'accepted');
    }

    private static function postIdForRemoteObject(string $uri): ?int
    {
        $row = DB::row('
SELECT `postId`
    FROM `Posts`
    WHERE `remoteObjectURI` = ?
', 'Post', 's', $uri);

        return $row !== null ? (int) $row -> postId : null;
    }

    public function testCreateNoteIngestsAndFansOutToTheAcceptedFollower(): void
    {
        $actor_uri = 'https://remote.test/users/' . bin2hex(random_bytes(6));
        self::createShadowUser($actor_uri);
        $follower_id = self::createUser();
        self::acceptFollow($follower_id, $actor_uri);

        $object_uri = 'https://remote.test/notes/' . bin2hex(random_bytes(6));

        ActivityPubInbox::process([
            'type' => 'Create',
            'object' => ['type' => 'Note', 'id' => $object_uri, 'content' => 'hello from the fediverse'],
        ], $actor_uri);

        $post_id = self::postIdForRemoteObject($object_uri);
        $this -> assertNotNull($post_id);

        $timeline_row = DB::row('
SELECT `postId`
    FROM `Timelines`
    WHERE `userId` = ? AND `postId` = ?
', 'Post', 'ii', $follower_id, $post_id);
        $this -> assertNotNull($timeline_row);
    }

    public function testRedeliveryOfTheSameObjectIsNotDuplicated(): void
    {
        $actor_uri = 'https://remote.test/users/' . bin2hex(random_bytes(6));
        self::createShadowUser($actor_uri);
        $object_uri = 'https://remote.test/notes/' . bin2hex(random_bytes(6));

        $activity = [
            'type' => 'Create',
            'object' => ['type' => 'Note', 'id' => $object_uri, 'content' => 'once'],
        ];

        ActivityPubInbox::process($activity, $actor_uri);
        $first_post_id = self::postIdForRemoteObject($object_uri);

        ActivityPubInbox::process($activity, $actor_uri);
        $second_post_id = self::postIdForRemoteObject($object_uri);

        $this -> assertSame($first_post_id, $second_post_id);
    }

    public function testATombstonedObjectIsNeverCopiedBackIn(): void
    {
        $actor_uri = 'https://remote.test/users/' . bin2hex(random_bytes(6));
        self::createShadowUser($actor_uri);
        $object_uri = 'https://remote.test/notes/' . bin2hex(random_bytes(6));

        RemoteObjectTombstone::tombstone($object_uri, 'test: deleted by a moderator');

        ActivityPubInbox::process([
            'type' => 'Create',
            'object' => ['type' => 'Note', 'id' => $object_uri, 'content' => 'should never appear'],
        ], $actor_uri);

        $this -> assertNull(self::postIdForRemoteObject($object_uri));
    }

    public function testDeletingAPostThroughPostDeleteTombstonesItsRemoteObjectURI(): void
    {
        $actor_uri = 'https://remote.test/users/' . bin2hex(random_bytes(6));
        self::createShadowUser($actor_uri);
        $object_uri = 'https://remote.test/notes/' . bin2hex(random_bytes(6));

        ActivityPubInbox::process([
            'type' => 'Create',
            'object' => ['type' => 'Note', 'id' => $object_uri, 'content' => 'will be deleted'],
        ], $actor_uri);

        $post_id = self::postIdForRemoteObject($object_uri);
        $this -> assertNotNull($post_id);

        Post::delete($post_id);

        $this -> assertTrue(RemoteObjectTombstone::isTombstoned($object_uri));

        // Confirms the tombstone actually prevents redelivery from resurrecting it -
        // not just that a row exists in RemoteObjectTombstones.
        ActivityPubInbox::process([
            'type' => 'Create',
            'object' => ['type' => 'Note', 'id' => $object_uri, 'content' => 'redelivered after deletion'],
        ], $actor_uri);

        $this -> assertNull(self::postIdForRemoteObject($object_uri));
    }

    public function testABannedActorsDeliveryIsRefused(): void
    {
        $actor_uri = 'https://remote.test/users/' . bin2hex(random_bytes(6));
        self::createShadowUser($actor_uri, banned: 1);
        $object_uri = 'https://remote.test/notes/' . bin2hex(random_bytes(6));

        ActivityPubInbox::process([
            'type' => 'Create',
            'object' => ['type' => 'Note', 'id' => $object_uri, 'content' => 'should be refused'],
        ], $actor_uri);

        $this -> assertNull(self::postIdForRemoteObject($object_uri));
    }

    public function testAReplyToAnUnknownPostIsIgnoredEntirely(): void
    {
        $actor_uri = 'https://remote.test/users/' . bin2hex(random_bytes(6));
        self::createShadowUser($actor_uri);
        $object_uri = 'https://remote.test/notes/' . bin2hex(random_bytes(6));

        ActivityPubInbox::process([
            'type' => 'Create',
            'object' => [
                'type' => 'Note',
                'id' => $object_uri,
                'content' => 'a reply to something this site has never seen',
                'inReplyTo' => 'https://somewhere-else.test/notes/does-not-exist-here',
            ],
        ], $actor_uri);

        $this -> assertNull(self::postIdForRemoteObject($object_uri));
    }

    public function testAReplyToAKnownRemotePostThreadsCorrectly(): void
    {
        $actor_uri = 'https://remote.test/users/' . bin2hex(random_bytes(6));
        self::createShadowUser($actor_uri);

        $parent_uri = 'https://remote.test/notes/' . bin2hex(random_bytes(6));
        ActivityPubInbox::process([
            'type' => 'Create',
            'object' => ['type' => 'Note', 'id' => $parent_uri, 'content' => 'parent note'],
        ], $actor_uri);
        $parent_post_id = self::postIdForRemoteObject($parent_uri);

        $reply_uri = 'https://remote.test/notes/' . bin2hex(random_bytes(6));
        ActivityPubInbox::process([
            'type' => 'Create',
            'object' => ['type' => 'Note', 'id' => $reply_uri, 'content' => 'a reply', 'inReplyTo' => $parent_uri],
        ], $actor_uri);
        $reply_post_id = self::postIdForRemoteObject($reply_uri);

        $this -> assertNotNull($reply_post_id);

        $reply_row = DB::row('
SELECT `parentId`
    FROM `Posts`
    WHERE `postId` = ?
', 'Post', 'i', $reply_post_id);
        $this -> assertSame($parent_post_id, $reply_row -> parentId);
    }

    public function testDeleteActivityFromTheOriginServerRemovesAndTombstonesThePost(): void
    {
        $actor_uri = 'https://remote.test/users/' . bin2hex(random_bytes(6));
        self::createShadowUser($actor_uri);
        $object_uri = 'https://remote.test/notes/' . bin2hex(random_bytes(6));

        ActivityPubInbox::process([
            'type' => 'Create',
            'object' => ['type' => 'Note', 'id' => $object_uri, 'content' => 'will be remotely deleted'],
        ], $actor_uri);
        $this -> assertNotNull(self::postIdForRemoteObject($object_uri));

        ActivityPubInbox::process([
            'type' => 'Delete',
            'object' => $object_uri,
        ], $actor_uri);

        $this -> assertNull(self::postIdForRemoteObject($object_uri));
        $this -> assertTrue(RemoteObjectTombstone::isTombstoned($object_uri));
    }

    public function testOneActorCannotDeleteAnotherActorsPost(): void
    {
        $victim_uri = 'https://remote.test/users/victim-' . bin2hex(random_bytes(6));
        self::createShadowUser($victim_uri);

        $attacker_uri = 'https://remote.test/users/attacker-' . bin2hex(random_bytes(6));
        self::createShadowUser($attacker_uri);

        $object_uri = 'https://remote.test/notes/' . bin2hex(random_bytes(6));
        ActivityPubInbox::process([
            'type' => 'Create',
            'object' => ['type' => 'Note', 'id' => $object_uri, 'content' => 'the victim post'],
        ], $victim_uri);

        $post_id = self::postIdForRemoteObject($object_uri);
        $this -> assertNotNull($post_id);

        ActivityPubInbox::process(['type' => 'Delete', 'object' => $object_uri], $attacker_uri);

        $this -> assertSame($post_id, self::postIdForRemoteObject($object_uri));
        $this -> assertFalse(RemoteObjectTombstone::isTombstoned($object_uri));
    }

    public function testOneActorCannotRewriteAnotherActorsPost(): void
    {
        $victim_uri = 'https://remote.test/users/victim-' . bin2hex(random_bytes(6));
        self::createShadowUser($victim_uri);

        $attacker_uri = 'https://remote.test/users/attacker-' . bin2hex(random_bytes(6));
        self::createShadowUser($attacker_uri);

        $object_uri = 'https://remote.test/notes/' . bin2hex(random_bytes(6));
        ActivityPubInbox::process([
            'type' => 'Create',
            'object' => ['type' => 'Note', 'id' => $object_uri, 'content' => 'the original wording'],
        ], $victim_uri);

        ActivityPubInbox::process([
            'type' => 'Update',
            'object' => ['type' => 'Note', 'id' => $object_uri, 'content' => 'attacker rewrote this'],
        ], $attacker_uri);

        $post = DB::row('
SELECT `description`
    FROM `Posts`
    WHERE `remoteObjectURI` = ?
', 'Post', 's', $object_uri);

        $this -> assertSame('the original wording', $post -> description);
    }

    public function testAnActorCanStillDeleteAndUpdateItsOwnPost(): void
    {
        $actor_uri = 'https://remote.test/users/' . bin2hex(random_bytes(6));
        self::createShadowUser($actor_uri);

        $updatable_uri = 'https://remote.test/notes/' . bin2hex(random_bytes(6));
        ActivityPubInbox::process([
            'type' => 'Create',
            'object' => ['type' => 'Note', 'id' => $updatable_uri, 'content' => 'before'],
        ], $actor_uri);

        ActivityPubInbox::process([
            'type' => 'Update',
            'object' => ['type' => 'Note', 'id' => $updatable_uri, 'content' => 'after'],
        ], $actor_uri);

        $updated = DB::row('
SELECT `description`
    FROM `Posts`
    WHERE `remoteObjectURI` = ?
', 'Post', 's', $updatable_uri);
        $this -> assertSame('after', $updated -> description);

        ActivityPubInbox::process(['type' => 'Delete', 'object' => $updatable_uri], $actor_uri);

        $this -> assertNull(self::postIdForRemoteObject($updatable_uri));
        $this -> assertTrue(RemoteObjectTombstone::isTombstoned($updatable_uri));
    }

    public function testANoteAttributedToSomeoneElseIsRefused(): void
    {
        $signer_uri = 'https://remote.test/users/signer-' . bin2hex(random_bytes(6));
        self::createShadowUser($signer_uri);

        $object_uri = 'https://remote.test/notes/' . bin2hex(random_bytes(6));

        ActivityPubInbox::process([
            'type' => 'Create',
            'object' => [
                'type' => 'Note',
                'id' => $object_uri,
                'attributedTo' => 'https://remote.test/users/somebody-else',
                'content' => 'claiming another actors object URI',
            ],
        ], $signer_uri);

        $this -> assertNull(self::postIdForRemoteObject($object_uri));
    }

    public function testAnOversizedRemoteNoteIsStoredRatherThanThrowing(): void
    {
        $actor_uri = 'https://remote.test/users/' . bin2hex(random_bytes(6));
        self::createShadowUser($actor_uri);
        $object_uri = 'https://remote.test/notes/' . bin2hex(random_bytes(6));

        ActivityPubInbox::process([
            'type' => 'Create',
            'object' => ['type' => 'Note', 'id' => $object_uri, 'content' => str_repeat('A', 200000)],
        ], $actor_uri);

        $post = DB::row('
SELECT `description`
    FROM `Posts`
    WHERE `remoteObjectURI` = ?
', 'Post', 's', $object_uri);

        $this -> assertNotNull($post);
        $this -> assertTrue(strlen((string) $post -> description) <= 65535);
    }

    /**
     * A canary over the whole public boundary, not just the one query the
     * behavioural test below covers: every surface that shows posts to people
     * who never followed the account has to exclude remote content, and only
     * one of them is reachable from a unit test. Deliberately a crude
     * occurrence count rather than SQL parsing - it exists to fail loudly if
     * a surface is added or rewritten without the filter, and something that
     * tried to parse the queries would just be brittle in its own right.
     *
     * FeedList needs two: its global feed and its tag pages. Its friends
     * query is scoped by Timelines (only actual followers have a row) and its
     * profile query intentionally shows a followed account's own posts, so
     * neither carries the filter.
     */
    public function testEveryPublicPostSurfaceStillFiltersRemoteContent(): void
    {
        $required_occurrences = [
            '/var/www/html/rss-feed.php' => 1,
            '/var/www/html/src/classes/Trending.php' => 1,
            '/var/www/html/api/search-posts.php' => 1,
            '/var/www/html/src/classes/FeedList.php' => 2,
        ];

        foreach ($required_occurrences as $path => $expected) {
            $source = (string) file_get_contents($path);

            $this -> assertSame($expected, substr_count($source, 'remoteObjectURI` IS NULL'));
        }
    }

    public function testRemotePostsNeverAppearInTheGlobalFeedQuery(): void
    {
        $actor_uri = 'https://remote.test/users/' . bin2hex(random_bytes(6));
        $author_id = self::createShadowUser($actor_uri);
        $object_uri = 'https://remote.test/notes/' . bin2hex(random_bytes(6));

        ActivityPubInbox::process([
            'type' => 'Create',
            'object' => ['type' => 'Note', 'id' => $object_uri, 'content' => 'remote content, never public'],
        ], $actor_uri);
        $post_id = self::postIdForRemoteObject($object_uri);

        $global_feed = new FeedList(['feedType' => 'global']);
        $ids_in_feed = array_map(static fn ($post) => $post -> postId, $global_feed -> contents);

        $this -> assertFalse(in_array($post_id, $ids_in_feed, true));
    }
}
