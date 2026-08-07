<?php

declare(strict_types=1);

/**
 * The firehose is kept to a window, and the window is the only thing that
 * decides it: anything a member here touched has stopped being firehose and
 * survives however old it gets, local accounts are never candidates at all,
 * and nothing swept is tombstoned - the same author has to be able to come
 * back tomorrow.
 */
class RelayRetentionTest extends DatabaseTestCase
{
    /**
     * Every DB test in a run shares one database and retention reads the whole
     * table, so these own RelayPosts for the length of a test and hand it back
     * empty - otherwise a row left behind by another class decides the window.
     */
    private function clearRelayPosts(): void
    {
        DB::run('
DELETE
    FROM `RelayPosts`
');
    }

    private function relay(): int
    {
        $actor_uri = 'https://retention.example/actor/' . bin2hex(random_bytes(4));

        DB::run('
INSERT INTO `Relays` (`actorURI`, `inboxURL`, `followActivityId`, `followObject`, `status`)
    VALUES (?, ?, ?, ?, ?)
', 'sssss', $actor_uri, $actor_uri . '/inbox', 'https://glommer.test/activitypub/actor#follows/'
            . bin2hex(random_bytes(4)), Relay::FOLLOW_PUBLIC, 'accepted');

        return (int) mysqli_insert_id(DB::connection());
    }

    /** A shadow account, the way a relayed post's author is stored here. */
    private function remoteAuthor(): int
    {
        $unique = bin2hex(random_bytes(6));

        // The unroutable synthetic address a shadow row really carries - there
        // is no mailbox, but the column is unique.
        DB::run('
INSERT INTO `Users` (`slug`, `email`, `passwordHash`, `remoteActorURI`)
    VALUES (?, ?, ?, ?)
', 'ssss', 'someone-' . $unique . '@remote.example', 'remote+' . $unique . '@glommer.test.invalid', '',
            'https://remote.example/users/someone-' . $unique);

        return (int) mysqli_insert_id(DB::connection());
    }

    private function relayedPost(int $relay_id, int $author_id): int
    {
        DB::run('
INSERT INTO `Posts` (`userId`, `description`, `remoteObjectURI`)
    VALUES (?, ?, ?)
', 'iss', $author_id, 'from the firehose', 'https://remote.example/notes/' . bin2hex(random_bytes(6)));

        $post_id = (int) mysqli_insert_id(DB::connection());

        DB::run('
INSERT INTO `RelayPosts` (`postId`, `relayId`)
    VALUES (?, ?)
', 'ii', $post_id, $relay_id);

        return $post_id;
    }

    private function postExists(int $post_id): bool
    {
        $result = mysqli_stmt_get_result(DB::run('
SELECT COUNT(*) AS `count`
    FROM `Posts`
    WHERE `postId` = ?
', 'i', $post_id));

        return (int) mysqli_fetch_assoc($result)['count'] === 1;
    }

    private function userExists(int $user_id): bool
    {
        $result = mysqli_stmt_get_result(DB::run('
SELECT COUNT(*) AS `count`
    FROM `Users`
    WHERE `userId` = ?
', 'i', $user_id));

        return (int) mysqli_fetch_assoc($result)['count'] === 1;
    }

    public function testOnlyTheNewestWindowfulIsKept(): void
    {
        $this -> clearRelayPosts();

        $relay = $this -> relay();
        $author = $this -> remoteAuthor();

        $oldest = $this -> relayedPost($relay, $author);
        $middle = $this -> relayedPost($relay, $author);
        $newest = $this -> relayedPost($relay, $author);

        $this -> assertSame(1, RelayRetention::prunePosts(2));

        $this -> assertFalse($this -> postExists($oldest));
        $this -> assertTrue($this -> postExists($middle));
        $this -> assertTrue($this -> postExists($newest));

        $this -> clearRelayPosts();
    }

    /** Fewer than a windowful means nothing is old enough to go. */
    public function testAnUnderfullWindowLosesNothing(): void
    {
        $this -> clearRelayPosts();

        $relay = $this -> relay();
        $post = $this -> relayedPost($relay, $this -> remoteAuthor());

        $this -> assertSame(0, RelayRetention::prunePosts(5000));
        $this -> assertTrue($this -> postExists($post));

        $this -> clearRelayPosts();
    }

    /**
     * A reply is somebody here writing, and the Posts cascade would take it
     * with the post it answered.
     */
    public function testAPostRepliedToHereSurvivesTheWindow(): void
    {
        $this -> clearRelayPosts();

        $relay = $this -> relay();
        $answered = $this -> relayedPost($relay, $this -> remoteAuthor());
        $ignored = $this -> relayedPost($relay, $this -> remoteAuthor());
        $newest = $this -> relayedPost($relay, $this -> remoteAuthor());

        DB::run('
INSERT INTO `Posts` (`userId`, `parentId`, `description`)
    VALUES (?, ?, ?)
', 'iis', self::createUser(), $answered, 'saying something back');

        RelayRetention::prunePosts(1);

        $this -> assertTrue($this -> postExists($answered));
        $this -> assertFalse($this -> postExists($ignored));
        $this -> assertTrue($this -> postExists($newest));

        $this -> clearRelayPosts();
    }

    public function testAPostLikedHereSurvivesTheWindow(): void
    {
        $this -> clearRelayPosts();

        $relay = $this -> relay();
        $liked = $this -> relayedPost($relay, $this -> remoteAuthor());
        $newest = $this -> relayedPost($relay, $this -> remoteAuthor());

        DB::run('
INSERT INTO `Likes` (`userId`, `postId`)
    VALUES (?, ?)
', 'ii', self::createUser(), $liked);

        RelayRetention::prunePosts(1);

        $this -> assertTrue($this -> postExists($liked));
        $this -> assertTrue($this -> postExists($newest));

        $this -> clearRelayPosts();
    }

    /**
     * A post can arrive through a follow and a relay at once - the relay only
     * adds a RelayPosts row to what is already held. Sweeping that by age
     * would take a followed account's writing out of the feed somebody chose.
     */
    public function testAPostInSomebodysTimelineSurvivesTheWindow(): void
    {
        $this -> clearRelayPosts();

        $relay = $this -> relay();
        $followed = $this -> relayedPost($relay, $this -> remoteAuthor());
        $newest = $this -> relayedPost($relay, $this -> remoteAuthor());

        DB::run('
INSERT INTO `Timelines` (`userId`, `postId`, `sortAt`)
    VALUES (?, ?, NOW())
', 'ii', self::createUser(), $followed);

        RelayRetention::prunePosts(1);

        $this -> assertTrue($this -> postExists($followed));
        $this -> assertTrue($this -> postExists($newest));

        $this -> clearRelayPosts();
    }

    /**
     * The account goes once the posts it was invented to carry have, and its
     * handle is not retired on the way out: retiring one would stop the same
     * author from ever being stored here again, and a relay will name them
     * again the moment they post.
     */
    public function testAnOrphanedShadowAccountGoesWithoutRetiringItsHandle(): void
    {
        $this -> clearRelayPosts();

        $author = $this -> remoteAuthor();
        $slug = (string) DB::row('
SELECT `slug`
    FROM `Users`
    WHERE `userId` = ?
', 'User', 'i', $author) -> slug;

        RelayRetention::pruneAccounts();

        $this -> assertFalse($this -> userExists($author));
        $this -> assertFalse(RetiredUsername::isRetired($slug));
    }

    /** A member who signed up here is never a candidate, however bare the row. */
    public function testALocalMemberIsNeverPruned(): void
    {
        $member = self::createUser();

        RelayRetention::pruneAccounts();

        $this -> assertTrue($this -> userExists($member));
    }

    public function testARemoteAccountSomebodyFollowsIsKept(): void
    {
        $followed = $this -> remoteAuthor();
        $actor_uri = (string) DB::row('
SELECT `remoteActorURI`
    FROM `Users`
    WHERE `userId` = ?
', 'User', 'i', $followed) -> remoteActorURI;

        DB::run('
INSERT INTO `RemoteFollows` (`localUserId`, `remoteActorURI`, `status`, `followActivityId`)
    VALUES (?, ?, ?, ?)
', 'isss', self::createUser(), $actor_uri, 'accepted',
            'https://glommer.test/activitypub/actor#follows/' . bin2hex(random_bytes(4)));

        RelayRetention::pruneAccounts();

        $this -> assertTrue($this -> userExists($followed));
    }

    /** A relay's own actor is not a stray account to collect. */
    public function testARelaysOwnActorIsKept(): void
    {
        $relay_id = $this -> relay();
        $actor_uri = (string) DB::row('
SELECT `actorURI`
    FROM `Relays`
    WHERE `relayId` = ?
', 'Relay', 'i', $relay_id) -> actorURI;

        $unique = bin2hex(random_bytes(6));

        DB::run('
INSERT INTO `Users` (`slug`, `email`, `passwordHash`, `remoteActorURI`)
    VALUES (?, ?, ?, ?)
', 'ssss', 'relay-' . $unique . '@retention.example', 'remote+' . $unique . '@glommer.test.invalid', '', $actor_uri);

        $relay_user = (int) mysqli_insert_id(DB::connection());

        RelayRetention::pruneAccounts();

        $this -> assertTrue($this -> userExists($relay_user));
    }
}
