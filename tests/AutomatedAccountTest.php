<?php

declare(strict_types=1);

/**
 * Keeping bots out of what people are talking about.
 *
 * Two thirds of the volume reaching a server carrying a relay is automated -
 * transit boards, weather stations, release feeds, bird detectors - and each
 * writes the same shape of line hundreds of times a day. The extractor reads
 * their station names and street names as topics, correctly, and none of them
 * is a thing anybody is discussing.
 *
 * They say what they are, so nothing here guesses: Mastodon's "this is a bot
 * account" checkbox publishes Service.
 */
class AutomatedAccountTest extends DatabaseTestCase
{
    private static function shadow(?string $actor_type): int
    {
        $unique = bin2hex(random_bytes(6));

        DB::run('
INSERT INTO `Users` (`slug`, `email`, `passwordHash`, `title`, `remoteActorURI`, `remoteActorInboxURL`, `remoteActorType`, `verified`)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
', 'sssssssi',
            'test-remote-' . $unique,
            'test-' . $unique . '@example.test',
            self::cheapHash('x'),
            'Remote Account',
            'https://remote.test/users/' . $unique,
            'https://remote.test/users/' . $unique . '/inbox',
            $actor_type,
            1
        );

        return (int) mysqli_insert_id(DB::connection());
    }

    private static function post(int $user_id, string $text): int
    {
        DB::run('
INSERT INTO `Posts` (`userId`, `description`, `descriptionDelta`, `remoteObjectURI`)
    VALUES (?, ?, ?, ?)
', 'isss', $user_id, $text, json_encode([['insert' => $text . "\n"]]),
            'https://remote.test/notes/' . bin2hex(random_bytes(6)));

        return (int) mysqli_insert_id(DB::connection());
    }

    /**
     * The post ids the extractor would read, from the real query rather than a
     * copy of it - a second copy here would keep passing while the one that
     * ships drifted.
     *
     * @return int[]
     */
    private static function corpusIds(): array
    {
        $corpus = new \ReflectionMethod(Trending::class, 'corpus');
        $corpus -> setAccessible(true);

        return array_map(static fn (Post $row): int => (int) $row -> postId, $corpus -> invoke(null));
    }

    public function testABotsPostsAreNotWhatPeopleAreTalkingAbout(): void
    {
        $bot = self::post(self::shadow('Service'), 'RER A: trafic interrompu Melun 06h32');
        $ids = self::corpusIds();

        $this -> assertFalse(in_array($bot, $ids, true));
    }

    /** An instance speaking as itself is not a person either. */
    public function testAnInstanceActorIsExcludedToo(): void
    {
        $instance = self::post(self::shadow('Application'), 'Server maintenance tonight');

        $this -> assertFalse(in_array($instance, self::corpusIds(), true));
    }

    public function testSomebodyTypingIsStillRead(): void
    {
        $person = self::post(self::shadow('Person'), 'I went to Berlin and it rained the whole time');

        $this -> assertTrue(in_array($person, self::corpusIds(), true));
    }

    /**
     * The question is "is this automated", not "is this a person" - an account
     * recorded before the type was kept has a null, and must stay in the
     * corpus rather than fall out of it while the backfill catches up.
     */
    public function testAnAccountNobodyHasFetchedYetStaysIn(): void
    {
        $unknown = self::post(self::shadow(null), 'Written before the type was recorded');

        $this -> assertTrue(in_array($unknown, self::corpusIds(), true));
    }

    /** A member here has no actor type at all and is never excluded by one. */
    public function testAMemberHereIsUnaffected(): void
    {
        $local = self::post(self::createUser(), 'A post from somebody with an account here');

        $this -> assertTrue(in_array($local, self::corpusIds(), true));
    }

    /**
     * The backfill has to get past accounts it cannot read.
     *
     * Shadow rows arrive from a relay in host order, so deleted accounts
     * cluster - one instance answering 410 Gone for a hundred of them would
     * sit at the head of an unordered queue and be handed back every pass,
     * and nothing behind it would ever be read.
     */
    public function testTheBackfillDoesNotKeepAskingForTheSameAccounts(): void
    {
        for ($i = 0; $i < 40; $i++) {
            self::shadow(null);
        }

        $choose = new \ReflectionMethod(RemoteActor::class, 'unknownTypeURIs');
        $choose -> setAccessible(true);

        $seen = [];

        for ($pass = 0; $pass < 6; $pass++) {
            foreach ($choose -> invoke(null, 10) as $actor_uri) {
                $seen[$actor_uri] = true;
            }
        }

        $this -> assertTrue(
            count($seen) > 10,
            'six passes that can only ever hand back the same ten would never drain the queue'
        );
    }

    /** What the far side calls itself is what gets recorded. */
    public function testTheTypeIsTakenFromTheActorDocument(): void
    {
        $uri = 'https://remote.test/users/' . bin2hex(random_bytes(6));

        RemoteActor::upsert([
            'id' => $uri,
            'inbox' => $uri . '/inbox',
            'sharedInbox' => null,
            'publicKeyPem' => 'not-a-real-key',
            'preferredUsername' => 'transitbot',
            'name' => 'Transit Bot',
            'iconURL' => null,
            'alsoKnownAs' => [],
            'actorType' => 'Service',
        ]);

        $this -> assertSame('Service', User::byRemoteActorURI($uri) ?-> remoteActorType);
    }
}
