<?php

declare(strict_types=1);

/**
 * A local member as a Fediverse actor: the identity a remote server
 * dereferences, and the rule that keeps two members of this server from
 * reaching each other the long way round.
 */
class ActivityPubActorTest extends DatabaseTestCase
{
    private static function localUser(): User
    {
        $user_id = self::createUser();

        $user = DB::row('
SELECT *
    FROM `Users`
    WHERE `userId` = ?
', 'User', 'i', $user_id);

        return $user;
    }

    public function testOurOwnHostIsRecognisedAsLocal(): void
    {
        $host = ActivityPubActor::canonicalHost();

        $this -> assertTrue(ActivityPubActor::isLocalActorURI('https://' . $host . '/users/someone/'));
    }

    public function testAHostnameIsMatchedCaseInsensitively(): void
    {
        // Hostnames are case-insensitive, so a server normalising differently
        // is naming this same server, not a different one - and the guard has
        // to hold either way or it can be walked straight past.
        $host = ActivityPubActor::canonicalHost();

        $this -> assertTrue(ActivityPubActor::isLocalActorURI('https://' . strtoupper($host) . '/users/someone/'));
    }

    public function testAnotherServerIsNotLocal(): void
    {
        $this -> assertFalse(ActivityPubActor::isLocalActorURI('https://mastodon.social/users/someone'));
    }

    public function testAHostThatMerelyContainsOursIsNotLocal(): void
    {
        // The check has to compare the whole authority, not look for a
        // substring: an attacker registering ourhost.evil.example would
        // otherwise be treated as us and skipped over.
        $host = ActivityPubActor::canonicalHost();

        $this -> assertFalse(ActivityPubActor::isLocalActorURI('https://' . $host . '.evil.example/users/someone'));
    }

    public function testSomethingThatIsNotAURIIsNotLocal(): void
    {
        $this -> assertFalse(ActivityPubActor::isLocalActorURI('not a uri'));
        $this -> assertFalse(ActivityPubActor::isLocalActorURI(''));
    }

    public function testAFollowOfOneOfOurOwnMembersIsRefused(): void
    {
        // Two people here already share a feed. An edge over the Fediverse on
        // top of that would deliver every post twice - once natively, once over
        // the wire - so it is refused before any network call is made.
        $host = ActivityPubActor::canonicalHost();
        $follower_id = self::createUser();

        $this -> assertFalse(RemoteFollow::createForActor($follower_id, 'https://' . $host . '/users/someone/'));
    }

    public function testAnActorDocumentCarriesWhatTheNetworkRequires(): void
    {
        $user = self::localUser();
        $document = ActivityPubActor::document($user);

        $this -> assertNotNull($document, 'a local member should have an actor document');

        foreach (['id', 'type', 'preferredUsername', 'inbox', 'outbox', 'followers', 'following', 'publicKey'] as $field) {
            $this -> assertTrue(isset($document[$field]), 'the actor document is missing ' . $field);
        }

        $this -> assertSame('Person', $document['type']);
        $this -> assertSame($user -> slug, $document['preferredUsername']);
        $this -> assertSame(ActivityPubActor::uriFor($user), $document['id']);
    }

    public function testTheActorIdIsTheProfileURL(): void
    {
        // One canonical address per person: the same URL a browser opens is
        // the one the network dereferences.
        $user = self::localUser();

        $this -> assertSame(ServerURL::absolute('/users/' . $user -> slug . '/'), ActivityPubActor::uriFor($user));
    }

    public function testAKeypairIsGeneratedOnceAndThenReused(): void
    {
        $user = self::localUser();

        $first = ActivityPubActor::publicKeyPem($user);
        $this -> assertNotNull($first, 'a member should get a key the first time they are dereferenced');

        // Re-read from the database rather than the in-memory object, so this
        // proves the key was stored rather than just remembered.
        $reloaded = DB::row('
SELECT *
    FROM `Users`
    WHERE `userId` = ?
', 'User', 'i', (int) $user -> userId);

        $this -> assertSame($first, ActivityPubActor::publicKeyPem($reloaded));
    }

    public function testThePrivateKeyIsNotStoredInTheClear(): void
    {
        $user = self::localUser();

        $private = ActivityPubActor::privateKeyPem($user);
        $this -> assertNotNull($private, 'a member should have a usable signing key');

        $stored = DB::row('
SELECT `actorEncryptedPrivateKey`
    FROM `Users`
    WHERE `userId` = ?
', 'User', 'i', (int) $user -> userId);

        // The point of encrypting it is that a database-only leak hands over
        // nothing usable, so what is on the row must not be the key itself.
        $this -> assertFalse(str_contains((string) $stored -> actorEncryptedPrivateKey, 'PRIVATE KEY'));
        $this -> assertTrue(str_contains($private, 'PRIVATE KEY'));
    }

    public function testARemoteAccountGetsNoActorDocumentFromUs(): void
    {
        // A shadow row is somebody else's account. Publishing an actor for it
        // would be this server claiming to speak for them.
        $user = new User();
        $user -> userId = 1;
        $user -> slug = 'someone';
        $user -> remoteActorURI = 'https://mastodon.social/users/someone';

        $this -> assertNull(ActivityPubActor::document($user));
    }

    public function testContentNegotiationPicksTheActorOnlyWhenAsked(): void
    {
        $this -> assertTrue(ActivityPubActor::wantsActivityJSON('application/activity+json'));
        $this -> assertTrue(ActivityPubActor::wantsActivityJSON('application/ld+json; profile="https://www.w3.org/ns/activitystreams"'));
        $this -> assertTrue(ActivityPubActor::wantsActivityJSON('text/html, application/activity+json;q=0.9'));

        $this -> assertFalse(ActivityPubActor::wantsActivityJSON('text/html'));
        $this -> assertFalse(ActivityPubActor::wantsActivityJSON('*/*'));
        $this -> assertFalse(ActivityPubActor::wantsActivityJSON(''));
    }

    public function testFollowersAndFollowingReadBackWhatWasRecorded(): void
    {
        $user_id = self::createUser();
        $actor = 'https://mastodon.social/users/follower-' . bin2hex(random_bytes(4));

        $this -> assertFalse(FediverseFollower::exists($user_id, $actor));

        FediverseFollower::add($user_id, $actor, $actor . '/inbox', null, 'https://mastodon.social/activities/' . bin2hex(random_bytes(4)));

        $this -> assertTrue(FediverseFollower::exists($user_id, $actor));
        $this -> assertSame([$actor], FediverseFollower::actorURIsFor($user_id));

        FediverseFollower::remove($user_id, $actor);

        $this -> assertSame([], FediverseFollower::actorURIsFor($user_id));
    }

    public function testDeliveryPrefersASharedInboxAndCollapsesDuplicates(): void
    {
        // Many followers on one server should cost one delivery, not one each -
        // that is the whole reason a shared inbox exists.
        $user_id = self::createUser();
        $shared = 'https://mastodon.social/inbox';

        foreach (['alice', 'bob', 'carol'] as $name) {
            $actor = 'https://mastodon.social/users/' . $name . '-' . bin2hex(random_bytes(4));
            FediverseFollower::add($user_id, $actor, $actor . '/inbox', $shared, 'https://mastodon.social/activities/' . bin2hex(random_bytes(4)));
        }

        $this -> assertSame([$shared], FediverseFollower::deliveryInboxesFor($user_id));
    }

    public function testAFollowerWithoutASharedInboxGetsItsOwn(): void
    {
        $user_id = self::createUser();
        $actor = 'https://example.test/users/solo-' . bin2hex(random_bytes(4));

        FediverseFollower::add($user_id, $actor, $actor . '/inbox', null, 'https://example.test/activities/' . bin2hex(random_bytes(4)));

        $this -> assertSame([$actor . '/inbox'], FediverseFollower::deliveryInboxesFor($user_id));
    }
}
