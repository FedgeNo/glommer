<?php

declare(strict_types=1);

/**
 * Pinning a post to the top of a profile, and publishing it as the actor's
 * featured collection.
 */
class PinnedPostTest extends DatabaseTestCase
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

    public function testPinningShowsAPostOnTheProfile(): void
    {
        $user = self::localUser();
        $post_id = self::post((int) $user -> userId);

        $this -> assertTrue(PinnedPost::pin((int) $user -> userId, $post_id));
        $this -> assertTrue(PinnedPost::isPinned((int) $user -> userId, $post_id));
        $this -> assertSame(1, count(PinnedPost::postsFor((int) $user -> userId)));
    }

    public function testUnpinningTakesItBackOff(): void
    {
        $user = self::localUser();
        $post_id = self::post((int) $user -> userId);

        PinnedPost::pin((int) $user -> userId, $post_id);
        PinnedPost::unpin((int) $user -> userId, $post_id);

        $this -> assertFalse(PinnedPost::isPinned((int) $user -> userId, $post_id));
    }

    public function testYouCannotPinSomebodyElsesPost(): void
    {
        // Pinning is a statement about your own profile, not a way to put
        // someone else's writing at the top of it.
        $author = self::localUser();
        $other = self::localUser();
        $post_id = self::post((int) $author -> userId);

        $this -> assertFalse(PinnedPost::pin((int) $other -> userId, $post_id));
        $this -> assertFalse(PinnedPost::isPinned((int) $other -> userId, $post_id));
    }

    public function testThereIsACapAndItHolds(): void
    {
        // A pinned list that can hold everything pins nothing.
        $user = self::localUser();

        foreach (range(1, PinnedPost::MAX_PINNED) as $index) {
            $this -> assertTrue(PinnedPost::pin((int) $user -> userId, self::post((int) $user -> userId)));
        }

        $this -> assertFalse(PinnedPost::pin((int) $user -> userId, self::post((int) $user -> userId)));
        $this -> assertSame(PinnedPost::MAX_PINNED, PinnedPost::countFor((int) $user -> userId));
    }

    public function testPinningTheSamePostTwiceChangesNothing(): void
    {
        $user = self::localUser();
        $post_id = self::post((int) $user -> userId);

        PinnedPost::pin((int) $user -> userId, $post_id);
        PinnedPost::pin((int) $user -> userId, $post_id);

        $this -> assertSame(1, PinnedPost::countFor((int) $user -> userId));
    }

    public function testTheFeaturedCollectionCarriesThePinnedPosts(): void
    {
        $user = self::localUser();
        $post_id = self::post((int) $user -> userId);

        PinnedPost::pin((int) $user -> userId, $post_id);

        $expected = ServerURL::absolute('/users/' . $user -> slug . '/' . $post_id);

        $this -> assertSame([$expected], PinnedPost::objectURIsFor($user));
    }

    public function testAPinnedPostFromElsewhereKeepsItsOwnURI(): void
    {
        // Pinning someone else's writing to your profile does not make it
        // yours to republish under our address.
        $user = self::localUser();
        $remote_uri = 'https://remote.invalid/statuses/' . bin2hex(random_bytes(4));
        $post_id = self::post((int) $user -> userId, $remote_uri);

        PinnedPost::pin((int) $user -> userId, $post_id);

        $this -> assertSame([$remote_uri], PinnedPost::objectURIsFor($user));
    }

    public function testTheActorAdvertisesItsFeaturedCollection(): void
    {
        $user = self::localUser();
        $document = ActivityPubActor::document($user);

        $this -> assertSame(ServerURL::absolute('/users/' . $user -> slug . '/featured'), $document['featured']);
    }

    public function testAPinnedPostThatNoLongerExistsSimplyDoesNotShow(): void
    {
        // Production drops the row by cascade, but the profile must not depend
        // on that: the list JOINs Posts, so a pin left dangling by a restore
        // without constraints, or by anything else, disappears rather than
        // breaking the page. Asserted here rather than the cascade itself,
        // which the test database cannot exercise - it is built with
        // CREATE TABLE ... LIKE, and that drops foreign keys.
        $user = self::localUser();
        $post_id = self::post((int) $user -> userId);

        PinnedPost::pin((int) $user -> userId, $post_id);
        Post::delete($post_id);

        $this -> assertSame([], PinnedPost::postsFor((int) $user -> userId));
        $this -> assertSame([], PinnedPost::objectURIsFor($user));
    }
}
