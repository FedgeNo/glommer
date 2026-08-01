<?php

declare(strict_types=1);

/**
 * Account migration.
 *
 * The load-bearing part is what it refuses. A Move is carried out by other
 * people's servers, so if one claim were enough to act on, anyone could send a
 * Move naming somebody else and redirect their whole following - and the person
 * losing it would have no way to intervene. Both accounts have to agree.
 */
class ActivityPubMoveTest extends DatabaseTestCase
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
', 'ssssssi', 'r-' . bin2hex(random_bytes(6)) . '@remote.invalid', 'test-' . bin2hex(random_bytes(6)) . '@example.test', password_hash('x', PASSWORD_DEFAULT), $actor, 'key', $actor . '/inbox', 1);

        return DB::row('
SELECT *
    FROM `Users`
    WHERE `userId` = ?
', 'User', 'i', (int) mysqli_insert_id(DB::connection()));
    }

    private static function reload(User $user): User
    {
        return DB::row('
SELECT *
    FROM `Users`
    WHERE `userId` = ?
', 'User', 'i', (int) $user -> userId);
    }

    // ----------------------------------------------------------------
    // Aliases - the permission half
    // ----------------------------------------------------------------

    public function testAnAliasIsRecordedAndReadBack(): void
    {
        $user = self::localUser();

        ActivityPubMove::setAliases($user, ['https://example.social/users/me']);

        $this -> assertSame(['https://example.social/users/me'], ActivityPubMove::aliasesFor(self::reload($user)));
    }

    public function testAnAliasPointingAtThisServerIsRefused(): void
    {
        // An account claiming to also be itself is nothing anything sensible
        // can come of.
        $user = self::localUser();
        $host = ActivityPubActor::canonicalHost();

        ActivityPubMove::setAliases($user, ['https://' . $host . '/users/someone/']);

        $this -> assertSame([], ActivityPubMove::aliasesFor(self::reload($user)));
    }

    public function testAnAliasThatIsNotAnHTTPURIIsRefused(): void
    {
        $user = self::localUser();

        ActivityPubMove::setAliases($user, ['not a uri', 'ftp://example.test/x', '', 'javascript:alert(1)']);

        $this -> assertSame([], ActivityPubMove::aliasesFor(self::reload($user)));
    }

    public function testTheSameAliasTwiceIsRecordedOnce(): void
    {
        $user = self::localUser();

        ActivityPubMove::setAliases($user, ['https://example.social/users/me', 'https://example.social/users/me']);

        $this -> assertSame(1, count(ActivityPubMove::aliasesFor(self::reload($user))));
    }

    public function testClearingAliasesRemovesThem(): void
    {
        $user = self::localUser();

        ActivityPubMove::setAliases($user, ['https://example.social/users/me']);
        ActivityPubMove::setAliases($user, []);

        $this -> assertSame([], ActivityPubMove::aliasesFor(self::reload($user)));
    }

    public function testTheActorAdvertisesItsAliases(): void
    {
        $user = self::localUser();
        ActivityPubMove::setAliases($user, ['https://example.social/users/me']);

        $document = ActivityPubActor::document(self::reload($user));

        $this -> assertSame(['https://example.social/users/me'], $document['alsoKnownAs']);
    }

    public function testAnActorWithNoAliasesSaysNothingAboutThem(): void
    {
        $document = ActivityPubActor::document(self::localUser());

        $this -> assertFalse(isset($document['alsoKnownAs']));
        $this -> assertFalse(isset($document['movedTo']));
    }

    public function testAMovedActorAdvertisesWhereItWent(): void
    {
        $user = self::localUser();

        DB::run('
UPDATE `Users`
    SET `movedToURI` = ?
    WHERE `userId` = ?
', 'si', 'https://example.social/users/me', (int) $user -> userId);

        $this -> assertSame('https://example.social/users/me', ActivityPubActor::document(self::reload($user))['movedTo']);
    }

    // ----------------------------------------------------------------
    // Moving out
    // ----------------------------------------------------------------

    public function testMovingToAnAccountOnThisServerIsRefused(): void
    {
        // A move only ever goes between servers - within one it would just be
        // a rename, which usernames here deliberately do not allow.
        $user = self::localUser();
        $host = ActivityPubActor::canonicalHost();

        $result = ActivityPubMove::publish($user, 'https://' . $host . '/users/someone/');

        $this -> assertFalse($result['ok']);
        $this -> assertNull(self::reload($user) -> movedToURI, 'nothing should have been recorded');
    }

    public function testMovingToAnUnreachableAccountIsRefused(): void
    {
        // The destination has to be fetched and has to name this account back.
        // Unreachable means unverifiable, so nothing is recorded and no Move is
        // sent - a Move every receiving server would correctly ignore would
        // leave the member believing they had migrated.
        $user = self::localUser();

        $before = FediverseDelivery::pendingCount();
        $result = ActivityPubMove::publish($user, 'https://unreachable.invalid/users/me');

        $this -> assertFalse($result['ok']);
        $this -> assertNull(self::reload($user) -> movedToURI);
        $this -> assertSame($before, FediverseDelivery::pendingCount());
    }

    // ----------------------------------------------------------------
    // Moving in - what has to be refused
    // ----------------------------------------------------------------

    public function testAMoveAboutSomebodyElseIsIgnored(): void
    {
        // The signature says who is talking; the activity says who it is about.
        // A server may only move its own account.
        $mover = self::remoteUser();
        $victim = self::remoteUser();
        $follower = self::localUser();

        DB::run('
INSERT INTO `RemoteFollows` (`localUserId`, `remoteActorURI`, `status`)
    VALUES (?, ?, ?)
', 'iss', (int) $follower -> userId, (string) $victim -> remoteActorURI, 'accepted');

        ActivityPubMove::received([
            'type' => 'Move',
            'actor' => $mover -> remoteActorURI,
            'object' => $victim -> remoteActorURI,
            'target' => 'https://elsewhere.invalid/users/attacker',
        ], $mover);

        $this -> assertSame(
            [(string) $victim -> remoteActorURI],
            RemoteFollow::acceptedActorURIsFor((int) $follower -> userId),
            'the victim should still be followed'
        );
    }

    public function testAMoveOntoThisServerIsIgnored(): void
    {
        // Arriving here is a local account declaring an alias, not a remote
        // server announcing it - otherwise anyone could attach their followers
        // to a member here.
        $mover = self::remoteUser();
        $follower = self::localUser();
        $host = ActivityPubActor::canonicalHost();

        DB::run('
INSERT INTO `RemoteFollows` (`localUserId`, `remoteActorURI`, `status`)
    VALUES (?, ?, ?)
', 'iss', (int) $follower -> userId, (string) $mover -> remoteActorURI, 'accepted');

        ActivityPubMove::received([
            'type' => 'Move',
            'actor' => $mover -> remoteActorURI,
            'object' => $mover -> remoteActorURI,
            'target' => 'https://' . $host . '/users/someone/',
        ], $mover);

        $this -> assertSame(
            [(string) $mover -> remoteActorURI],
            RemoteFollow::acceptedActorURIsFor((int) $follower -> userId)
        );
    }

    public function testAMoveToAnUnverifiableDestinationMovesNobody(): void
    {
        // Fails closed. The destination must be fetched and must list the
        // account that is leaving; an unreachable one proves nothing.
        $mover = self::remoteUser();
        $follower = self::localUser();

        DB::run('
INSERT INTO `RemoteFollows` (`localUserId`, `remoteActorURI`, `status`)
    VALUES (?, ?, ?)
', 'iss', (int) $follower -> userId, (string) $mover -> remoteActorURI, 'accepted');

        ActivityPubMove::received([
            'type' => 'Move',
            'actor' => $mover -> remoteActorURI,
            'object' => $mover -> remoteActorURI,
            'target' => 'https://unreachable.invalid/users/me',
        ], $mover);

        $this -> assertSame(
            [(string) $mover -> remoteActorURI],
            RemoteFollow::acceptedActorURIsFor((int) $follower -> userId),
            'an unverified move must leave the follow where it was'
        );
    }

    public function testAMoveWithNoDestinationIsIgnored(): void
    {
        $mover = self::remoteUser();

        ActivityPubMove::received(['type' => 'Move', 'actor' => $mover -> remoteActorURI], $mover);

        // Reaching here without a crash is the assertion.
        $this -> assertTrue(true);
    }
}
