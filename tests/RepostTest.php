<?php

declare(strict_types=1);

/**
 * Passing a post on.
 *
 * The interesting part is undoing it. Timelines is one row per (feed, post), so
 * a repost of something already in a feed must not take that post away when the
 * repost is withdrawn - which is what the reposterId column exists to make
 * possible.
 */
class RepostTest extends DatabaseTestCase
{
    private static function localUser(): User
    {
        return DB::row('
SELECT *
    FROM `Users`
    WHERE `userId` = ?
', 'User', 'i', self::createUser());
    }

    private static function post(int $user_id, ?string $remote_uri = null): int
    {
        DB::run('
INSERT INTO `Posts` (`userId`, `description`, `descriptionDelta`, `remoteObjectURI`)
    VALUES (?, ?, ?, ?)
', 'isss', $user_id, 'text', json_encode([['insert' => "text\n"]]), $remote_uri);

        return (int) mysqli_insert_id(DB::connection());
    }

    private static function inFeed(int $user_id, int $post_id): bool
    {
        return DB::row('
SELECT `postId`
    FROM `Timelines`
    WHERE `userId` = ? AND `postId` = ?
', 'PinnedPostData', 'ii', $user_id, $post_id) !== null;
    }

    public function testRepostingPutsThePostInTheReposterOwnFeed(): void
    {
        $author = self::localUser();
        $reposter = self::localUser();
        $post_id = self::post((int) $author -> userId);

        $this -> assertTrue(Repost::create((int) $reposter -> userId, $post_id));
        $this -> assertTrue(self::inFeed((int) $reposter -> userId, $post_id));
    }

    public function testUndoingARepostTakesItBackOut(): void
    {
        $author = self::localUser();
        $reposter = self::localUser();
        $post_id = self::post((int) $author -> userId);

        Repost::create((int) $reposter -> userId, $post_id);
        Repost::remove((int) $reposter -> userId, $post_id);

        $this -> assertFalse(Repost::exists((int) $reposter -> userId, $post_id));
        $this -> assertFalse(self::inFeed((int) $reposter -> userId, $post_id));
    }

    public function testUndoingARepostLeavesAPostThatWasAlreadyInTheFeed(): void
    {
        // The whole reason reposterId exists. Without it, withdrawing a repost
        // would delete a row that was there because the author is a friend.
        $author = self::localUser();
        $reader = self::localUser();
        $post_id = self::post((int) $author -> userId);

        // Already in the reader's feed on its own account.
        DB::run('
INSERT INTO `Timelines` (`userId`, `postId`)
    VALUES (?, ?)
', 'ii', (int) $reader -> userId, $post_id);

        // Someone the reader follows reposts it - the reader already has it, so
        // the existing row stands.
        Repost::create((int) $reader -> userId, $post_id);
        Repost::remove((int) $reader -> userId, $post_id);

        $this -> assertTrue(self::inFeed((int) $reader -> userId, $post_id), 'the original reason it was shown must survive');
    }

    public function testYouCannotRepostYourOwnPost(): void
    {
        $author = self::localUser();
        $post_id = self::post((int) $author -> userId);

        $this -> assertFalse(Repost::create((int) $author -> userId, $post_id));
    }

    public function testRepostingTwiceIsStillOneRepost(): void
    {
        $author = self::localUser();
        $reposter = self::localUser();
        $post_id = self::post((int) $author -> userId);

        Repost::create((int) $reposter -> userId, $post_id);
        Repost::create((int) $reposter -> userId, $post_id);

        $this -> assertSame(1, ActivityPubReaction::announceCount($post_id));
    }

    public function testARepostFromHereAndABoostFromElsewhereCountTogether(): void
    {
        // Same act, same table - so the number on the post is one total rather
        // than two added up.
        $author = self::localUser();
        $reposter = self::localUser();
        $post_id = self::post((int) $author -> userId);

        Repost::create((int) $reposter -> userId, $post_id);

        $actor = 'https://remote.invalid/users/r-' . bin2hex(random_bytes(5));

        DB::run('
INSERT INTO `Users` (`slug`, `email`, `passwordHash`, `remoteActorURI`, `verified`)
    VALUES (?, ?, ?, ?, ?)
', 'ssssi', 'r-' . bin2hex(random_bytes(6)) . '@remote.invalid', 'test-' . bin2hex(random_bytes(6)) . '@example.test', password_hash('x', PASSWORD_DEFAULT), $actor, 1);

        $them = DB::row('
SELECT *
    FROM `Users`
    WHERE `userId` = ?
', 'User', 'i', (int) mysqli_insert_id(DB::connection()));

        ActivityPubReaction::announced(
            ServerURL::absolute('/users/' . $author -> slug . '/' . $post_id),
            $them,
            'https://remote.invalid/activities/' . bin2hex(random_bytes(4))
        );

        $this -> assertSame(2, ActivityPubReaction::announceCount($post_id));
    }

    public function testAnAnnounceIsQueuedForFollowers(): void
    {
        $author = self::localUser();
        $reposter = self::localUser();
        $post_id = self::post((int) $author -> userId);

        FediverseFollower::add((int) $reposter -> userId, 'https://remote.invalid/users/f', 'https://remote.invalid/inbox-' . bin2hex(random_bytes(4)), null, 'x');

        $before = FediverseDelivery::pendingCount();
        Repost::publish($reposter, $post_id, true);

        $this -> assertSame($before + 1, FediverseDelivery::pendingCount());
    }

    public function testUndoingSendsAnUndoCarryingTheAnnounce(): void
    {
        $author = self::localUser();
        $reposter = self::localUser();
        $post_id = self::post((int) $author -> userId);

        FediverseFollower::add((int) $reposter -> userId, 'https://remote.invalid/users/f2', 'https://remote.invalid/inbox-' . bin2hex(random_bytes(4)), null, 'x');

        Repost::publish($reposter, $post_id, false);

        $row = DB::row('
SELECT *
    FROM `FediverseDeliveries`
    ORDER BY `deliveryId` DESC
    LIMIT 1
', 'FediverseDeliveryData');

        $activity = json_decode((string) $row -> activity, true);

        $this -> assertSame('Undo', $activity['type']);
        $this -> assertSame('Announce', $activity['object']['type']);
    }

    public function testBoostingARemotePostNamesItsOwnURI(): void
    {
        // Not our local copy - every other server has to recognise what is
        // being passed on.
        $author = self::localUser();
        $reposter = self::localUser();
        $remote_uri = 'https://remote.invalid/statuses/' . bin2hex(random_bytes(4));
        $post_id = self::post((int) $author -> userId, $remote_uri);

        FediverseFollower::add((int) $reposter -> userId, 'https://remote.invalid/users/f3', 'https://remote.invalid/inbox-' . bin2hex(random_bytes(4)), null, 'x');

        Repost::publish($reposter, $post_id, true);

        $row = DB::row('
SELECT *
    FROM `FediverseDeliveries`
    ORDER BY `deliveryId` DESC
    LIMIT 1
', 'FediverseDeliveryData');

        $this -> assertSame($remote_uri, json_decode((string) $row -> activity, true)['object']);
    }
}
