<?php

declare(strict_types=1);

/**
 * Likes and boosts crossing the boundary. The interesting cases are the ones
 * where a URI names something that is not ours to react to.
 */
class ActivityPubReactionTest extends DatabaseTestCase
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
', 'ssssssi', 'r-' . bin2hex(random_bytes(6)) . '@remote.invalid', 'test-' . bin2hex(random_bytes(6)) . '@example.test', self::cheapHash('x'), $actor, 'key', $actor . '/inbox', 1);

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
', 'isss', $user_id, 'text', json_encode([['insert' => "text\n"]]), $remote_uri);

        return (int) mysqli_insert_id(DB::connection());
    }

    private static function likeCount(int $post_id): int
    {
        $row = DB::row('
SELECT COUNT(*) AS `total`
    FROM `Likes`
    WHERE `postId` = ?
', 'PostCountData', 'i', $post_id);

        return (int) $row -> total;
    }

    public function testALikeFromElsewhereCountsTheSameAsALocalOne(): void
    {
        $author = self::localUser();
        $them = self::remoteUser();
        $post_id = self::post((int) $author -> userId);

        ActivityPubReaction::liked(ServerURL::absolute('/users/' . $author -> slug . '/' . $post_id), $them);

        $this -> assertSame(1, self::likeCount($post_id));
    }

    public function testTheSameLikeTwiceIsStillOneLike(): void
    {
        // A server re-sending a Like it already sent is how it recovers after
        // losing state, not an error.
        $author = self::localUser();
        $them = self::remoteUser();
        $post_id = self::post((int) $author -> userId);
        $uri = ServerURL::absolute('/users/' . $author -> slug . '/' . $post_id);

        ActivityPubReaction::liked($uri, $them);
        ActivityPubReaction::liked($uri, $them);

        $this -> assertSame(1, self::likeCount($post_id));
    }

    public function testAnUndoTakesTheLikeBack(): void
    {
        $author = self::localUser();
        $them = self::remoteUser();
        $post_id = self::post((int) $author -> userId);
        $uri = ServerURL::absolute('/users/' . $author -> slug . '/' . $post_id);

        ActivityPubReaction::liked($uri, $them);
        ActivityPubReaction::unliked($uri, $them);

        $this -> assertSame(0, self::likeCount($post_id));
    }

    public function testALikeAimedAtAnotherServersObjectIsIgnored(): void
    {
        // It belongs to that object's own server; recording it here would
        // double-count it.
        $author = self::localUser();
        $them = self::remoteUser();
        $post_id = self::post((int) $author -> userId, 'https://elsewhere.invalid/statuses/1');

        ActivityPubReaction::liked('https://elsewhere.invalid/statuses/1', $them);

        $this -> assertSame(0, self::likeCount($post_id));
    }

    public function testAURINamingTheRightPostUnderTheWrongPersonResolvesToNothing(): void
    {
        $author = self::localUser();
        $other = self::localUser();
        $them = self::remoteUser();
        $post_id = self::post((int) $author -> userId);

        ActivityPubReaction::liked(ServerURL::absolute('/users/' . $other -> slug . '/' . $post_id), $them);

        $this -> assertSame(0, self::likeCount($post_id));
    }

    public function testABannedAccountsLikeIsNotCounted(): void
    {
        $author = self::localUser();
        $them = self::remoteUser();
        $post_id = self::post((int) $author -> userId);

        DB::run('
UPDATE `Users`
    SET `banned` = 1
    WHERE `userId` = ?
', 'i', (int) $them -> userId);

        $banned = DB::row('
SELECT *
    FROM `Users`
    WHERE `userId` = ?
', 'User', 'i', (int) $them -> userId);

        ActivityPubReaction::liked(ServerURL::absolute('/users/' . $author -> slug . '/' . $post_id), $banned);

        $this -> assertSame(0, self::likeCount($post_id));
    }

    public function testABoostIsRecordedAndCanBeWithdrawn(): void
    {
        $author = self::localUser();
        $them = self::remoteUser();
        $post_id = self::post((int) $author -> userId);
        $uri = ServerURL::absolute('/users/' . $author -> slug . '/' . $post_id);

        ActivityPubReaction::announced($uri, $them, 'https://remote.invalid/activities/' . bin2hex(random_bytes(4)));
        $this -> assertSame(1, ActivityPubReaction::announceCount($post_id));

        ActivityPubReaction::unannounced($uri, $them);
        $this -> assertSame(0, ActivityPubReaction::announceCount($post_id));
    }

    public function testLikingARemotePostTellsItsServer(): void
    {
        $liker = self::localUser();
        $them = self::remoteUser();
        $post_id = self::post((int) $them -> userId, 'https://remote.invalid/statuses/' . bin2hex(random_bytes(4)));

        $before = FediverseDelivery::pendingCount();
        ActivityPubReaction::publishLike($post_id, $liker, true);

        $this -> assertSame($before + 1, FediverseDelivery::pendingCount());
    }

    public function testLikingALocalPostTellsNobody(): void
    {
        $liker = self::localUser();
        $author = self::localUser();
        $post_id = self::post((int) $author -> userId);

        $before = FediverseDelivery::pendingCount();
        ActivityPubReaction::publishLike($post_id, $liker, true);

        $this -> assertSame($before, FediverseDelivery::pendingCount());
    }

    public function testUnlikingARemotePostSendsAnUndoCarryingTheLike(): void
    {
        // The far side matches the Undo against what it recorded, so the Like
        // has to be restated inside it rather than merely referenced.
        $liker = self::localUser();
        $them = self::remoteUser();
        $post_id = self::post((int) $them -> userId, 'https://remote.invalid/statuses/' . bin2hex(random_bytes(4)));

        ActivityPubReaction::publishLike($post_id, $liker, false);

        $row = DB::row('
SELECT *
    FROM `FediverseDeliveries`
    ORDER BY `deliveryId` DESC
    LIMIT 1
', 'FediverseDeliveryData');

        $activity = json_decode((string) $row -> activity, true);

        $this -> assertSame('Undo', $activity['type']);
        $this -> assertSame('Like', $activity['object']['type']);
    }
}
