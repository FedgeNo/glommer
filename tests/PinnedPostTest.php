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

    private static function pinChange(string $type, User $actor, string $object_uri, string $target): void
    {
        ActivityPubInbox::process([
            'type' => $type,
            'actor' => $actor -> remoteActorURI,
            'object' => $object_uri,
            'target' => $target,
        ], (string) $actor -> remoteActorURI);
    }

    public function testAPinFromElsewhereShowsOnTheirProfileHere(): void
    {
        $them = self::remoteUser();
        $object_uri = 'https://remote.invalid/statuses/' . bin2hex(random_bytes(4));
        $post_id = self::post((int) $them -> userId, $object_uri);

        self::pinChange('Add', $them, $object_uri, 'https://remote.invalid/users/x/collections/featured');

        $this -> assertTrue(PinnedPost::isPinned((int) $them -> userId, $post_id));
    }

    public function testAnUnpinFromElsewhereTakesItBackOff(): void
    {
        $them = self::remoteUser();
        $object_uri = 'https://remote.invalid/statuses/' . bin2hex(random_bytes(4));
        $post_id = self::post((int) $them -> userId, $object_uri);

        PinnedPost::pin((int) $them -> userId, $post_id);
        self::pinChange('Remove', $them, $object_uri, 'https://remote.invalid/users/x/collections/featured');

        $this -> assertFalse(PinnedPost::isPinned((int) $them -> userId, $post_id));
    }

    public function testAnAddNamingSomebodyElsesCollectionIsIgnored(): void
    {
        // The target has to be on the sender's own server, or one server could
        // rearrange another's profile.
        $them = self::remoteUser();
        $object_uri = 'https://remote.invalid/statuses/' . bin2hex(random_bytes(4));
        $post_id = self::post((int) $them -> userId, $object_uri);

        self::pinChange('Add', $them, $object_uri, 'https://elsewhere.invalid/users/x/collections/featured');

        $this -> assertFalse(PinnedPost::isPinned((int) $them -> userId, $post_id));
    }

    public function testAnAddOfSomebodyElsesPostIsIgnored(): void
    {
        $them = self::remoteUser();
        $author = self::localUser();
        $object_uri = 'https://remote.invalid/statuses/' . bin2hex(random_bytes(4));
        $post_id = self::post((int) $author -> userId, $object_uri);

        self::pinChange('Add', $them, $object_uri, 'https://remote.invalid/users/x/collections/featured');

        $this -> assertFalse(PinnedPost::isPinned((int) $them -> userId, $post_id));
        $this -> assertFalse(PinnedPost::isPinned((int) $author -> userId, $post_id));
    }

    public function testPinningLocallyTellsTheNetworkWhichPost(): void
    {
        // The featured collection is served and could be re-read, but nothing
        // would prompt anyone to re-read it.
        $user = self::localUser();
        $post_id = self::post((int) $user -> userId);
        FediverseFollower::add((int) $user -> userId, 'https://remote.invalid/users/watcher', 'https://remote.invalid/inbox', null, 'https://remote.invalid/follows/1');

        $before = FediverseDelivery::pendingCount();
        PinnedPost::publish($user, $post_id, true);

        $this -> assertSame($before + 1, FediverseDelivery::pendingCount());
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

    public function testAPinNobodyCanSeeDoesNotHoldASlot(): void
    {
        // The cap is refused-when-full rather than push-the-oldest-off, so a
        // slot held by an invisible pin is one its owner can never free: there
        // is nothing on the profile to click unpin on.
        $user = self::localUser();

        foreach (range(1, PinnedPost::MAX_PINNED) as $index) {
            PinnedPost::pin((int) $user -> userId, self::post((int) $user -> userId));
        }

        Post::delete((int) PinnedPost::postsFor((int) $user -> userId)[0] -> postId);

        $this -> assertSame(PinnedPost::MAX_PINNED - 1, PinnedPost::countFor((int) $user -> userId));
        $this -> assertTrue(PinnedPost::pin((int) $user -> userId, self::post((int) $user -> userId)));
    }
}
