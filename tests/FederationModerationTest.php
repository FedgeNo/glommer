<?php

declare(strict_types=1);

/**
 * Moderation that has to work across servers: reports that travel, and whole
 * servers that can be shut out.
 */
class FederationModerationTest extends DatabaseTestCase
{
    private static function localUser(): User
    {
        return DB::row('
SELECT *
    FROM `Users`
    WHERE `userId` = ?
', 'User', 'i', self::createUser());
    }

    private static function remoteUser(string $host = 'remote.invalid'): User
    {
        $actor = 'https://' . $host . '/users/r-' . bin2hex(random_bytes(5));

        DB::run('
INSERT INTO `Users` (`slug`, `email`, `passwordHash`, `remoteActorURI`, `remoteActorPublicKeyPem`, `remoteActorInboxURL`, `verified`)
    VALUES (?, ?, ?, ?, ?, ?, ?)
', 'ssssssi', 'r-' . bin2hex(random_bytes(6)) . '@' . $host, 'test-' . bin2hex(random_bytes(6)) . '@example.test', password_hash('x', PASSWORD_DEFAULT), $actor, 'key', $actor . '/inbox', 1);

        return DB::row('
SELECT *
    FROM `Users`
    WHERE `userId` = ?
', 'User', 'i', (int) mysqli_insert_id(DB::connection()));
    }

    private static function post(int $user_id): int
    {
        DB::run('
INSERT INTO `Posts` (`userId`, `description`, `descriptionDelta`)
    VALUES (?, ?, ?)
', 'iss', $user_id, 'text', json_encode([['insert' => "text\n"]]));

        return (int) mysqli_insert_id(DB::connection());
    }

    private static function reportCount(string $type, int $id): int
    {
        $row = DB::row('
SELECT COUNT(*) AS `total`
    FROM `Reports`
    WHERE `type` = ? AND `targetId` = ?
', 'PostCountData', 'si', $type, $id);

        return (int) $row -> total;
    }

    // ----------------------------------------------------------------
    // Flag
    // ----------------------------------------------------------------

    public function testAReportFromAnotherServerReachesTheModerationQueue(): void
    {
        $author = self::localUser();
        $them = self::remoteUser();
        $post_id = self::post((int) $author -> userId);

        ActivityPubFlag::received([
            'type' => 'Flag',
            'content' => 'spam',
            'object' => [ServerURL::absolute('/users/' . $author -> slug . '/' . $post_id)],
        ], $them);

        $this -> assertSame(1, self::reportCount('post', $post_id));
    }

    public function testAFederatedReportSaysWhereItCameFrom(): void
    {
        // A moderator has to be able to see which server is complaining, not
        // least so they can ban it when the complaints are themselves the abuse.
        $author = self::localUser();
        $them = self::remoteUser();
        $post_id = self::post((int) $author -> userId);

        ActivityPubFlag::received([
            'type' => 'Flag',
            'content' => 'spam',
            'object' => ServerURL::absolute('/users/' . $author -> slug . '/' . $post_id),
        ], $them);

        $report = DB::row('
SELECT `reason`
    FROM `Reports`
    WHERE `type` = ? AND `targetId` = ?
', 'ReportData', 'si', 'post', $post_id);

        $this -> assertTrue(str_contains((string) $report -> reason, 'Fediverse'));
    }

    public function testAReportAboutSomethingNotOursIsIgnored(): void
    {
        $them = self::remoteUser();
        $before = self::reportCount('post', 0);

        ActivityPubFlag::received([
            'type' => 'Flag',
            'object' => ['https://elsewhere.invalid/statuses/1'],
        ], $them);

        $this -> assertSame($before, self::reportCount('post', 0));
    }

    public function testOneDeliveryCannotFillTheQueue(): void
    {
        // A hostile server naming a thousand objects in one Flag should not be
        // able to bury the queue from a single request.
        $author = self::localUser();
        $them = self::remoteUser();
        $post_id = self::post((int) $author -> userId);

        $objects = array_fill(0, 100, ServerURL::absolute('/users/' . $author -> slug . '/' . $post_id));

        ActivityPubFlag::received(['type' => 'Flag', 'object' => $objects], $them);

        // Report::create dedupes per reporter, so the cap shows as the queue
        // simply not exploding rather than as a count of 20.
        $this -> assertTrue(self::reportCount('post', $post_id) <= 1);
    }

    // ----------------------------------------------------------------
    // Defederation
    // ----------------------------------------------------------------

    public function testABlockedDomainIsRecognised(): void
    {
        $domain = 'bad-' . bin2hex(random_bytes(4)) . '.example';
        BlockedDomain::block($domain, 'spam', null);

        $this -> assertTrue(BlockedDomain::blocks($domain));
        $this -> assertTrue(BlockedDomain::blocksURL('https://' . $domain . '/users/someone'));
    }

    public function testBlockingAServerBlocksWhatItHandsOut(): void
    {
        // A host giving out anything.badserver.example is the same problem as
        // badserver.example itself.
        $domain = 'bad-' . bin2hex(random_bytes(4)) . '.example';
        BlockedDomain::block($domain, null, null);

        $this -> assertTrue(BlockedDomain::blocks('shard7.' . $domain));
        $this -> assertTrue(BlockedDomain::blocksURL('https://a.b.' . $domain . '/inbox'));
    }

    public function testAHostThatMerelyEndsSimilarlyIsNotBlocked(): void
    {
        $domain = 'bad-' . bin2hex(random_bytes(4)) . '.example';
        BlockedDomain::block($domain, null, null);

        $this -> assertFalse(BlockedDomain::blocks('not' . $domain));
    }

    public function testMatchingIgnoresCaseAndPort(): void
    {
        $domain = 'bad-' . bin2hex(random_bytes(4)) . '.example';
        BlockedDomain::block(strtoupper($domain), null, null);

        $this -> assertTrue(BlockedDomain::blocks($domain));
        $this -> assertTrue(BlockedDomain::blocks($domain . ':8443'));
    }

    public function testAPastedURLIsAcceptedAsADomain(): void
    {
        // A moderator reaching for this has usually just copied an address.
        $domain = 'bad-' . bin2hex(random_bytes(4)) . '.example';
        BlockedDomain::block('https://' . $domain . '/users/someone', null, null);

        $this -> assertTrue(BlockedDomain::blocks($domain));
    }

    public function testRubbishNeverBecomesARuleThatMatchesEverything(): void
    {
        foreach (['', '   ', 'localhost', '.', '://'] as $bad) {
            BlockedDomain::block($bad, null, null);
        }

        $this -> assertFalse(BlockedDomain::blocks('mastodon.social'));
        $this -> assertFalse(BlockedDomain::blocksURL('https://example.org/inbox'));
    }

    public function testUnblockingLetsAServerBackIn(): void
    {
        $domain = 'bad-' . bin2hex(random_bytes(4)) . '.example';
        BlockedDomain::block($domain, null, null);
        BlockedDomain::unblock($domain);

        $this -> assertFalse(BlockedDomain::blocks($domain));
    }

    public function testNothingIsQueuedForABlockedServer(): void
    {
        $user = self::localUser();
        $domain = 'bad-' . bin2hex(random_bytes(4)) . '.example';

        BlockedDomain::block($domain, null, null);

        $before = FediverseDelivery::pendingCount();
        FediverseDelivery::enqueue((int) $user -> userId, ['type' => 'Create'], ['https://' . $domain . '/inbox']);

        $this -> assertSame($before, FediverseDelivery::pendingCount());
    }
}
