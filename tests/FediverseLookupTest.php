<?php

declare(strict_types=1);

/**
 * Searching for somebody by handle. What matters here is when this server
 * does NOT go and ask: the lookup is an outbound request driven by whatever
 * was typed into a search box that fires on every keystroke, so the rules
 * about what counts as a handle are the safety of the thing.
 *
 * The fetch itself is not exercised - there is no server to answer under
 * test, and a lookup that cannot reach anybody must come back as no result
 * rather than as an error.
 */
class FediverseLookupTest extends DatabaseTestCase
{
    private function remoteAccount(string $handle, string $actor_uri): int
    {
        $unique = bin2hex(random_bytes(6));

        DB::run('
INSERT INTO `Users` (`slug`, `email`, `passwordHash`, `remoteActorURI`, `verified`)
    VALUES (?, ?, ?, ?, ?)
', 'ssssi', $handle, 'remote+' . $unique . '@glommer.test.invalid', '', $actor_uri, 1);

        return (int) mysqli_insert_id(DB::connection());
    }

    /** Already here is answered from here, without troubling anybody. */
    public function testAHandleThisServerAlreadyKnowsIsReturnedWithoutAsking(): void
    {
        $unique = bin2hex(random_bytes(4));
        $handle = 'alice' . $unique . '@example.social';
        $user_id = $this -> remoteAccount($handle, 'https://example.social/users/alice' . $unique);

        $found = FediverseLookup::find('@' . $handle);

        $this -> assertNotNull($found);
        $this -> assertSame($user_id, (int) $found -> userId);
    }

    /** Written without the leading @, which is how half the world writes one. */
    public function testTheLeadingAtIsOptional(): void
    {
        $unique = bin2hex(random_bytes(4));
        $handle = 'bob' . $unique . '@example.social';
        $user_id = $this -> remoteAccount($handle, 'https://example.social/users/bob' . $unique);

        $this -> assertSame($user_id, (int) FediverseLookup::find($handle) -> userId);
        $this -> assertSame($user_id, (int) FediverseLookup::find('  @' . $handle . '  ') -> userId);
    }

    /**
     * Handles are written with capitals - @Gargron@mastodon.social is how that
     * one appears everywhere - while parsing lowercases them. Comparing the
     * two literally rejected every handle anybody actually types, which is
     * exactly what the fixtures here missed by being lowercase hex.
     */
    public function testACapitalisedHandleIsStillTheSameHandle(): void
    {
        $unique = bin2hex(random_bytes(4));
        $handle = 'dave' . $unique . '@example.social';
        $user_id = $this -> remoteAccount($handle, 'https://example.social/users/dave' . $unique);

        $this -> assertSame($user_id, (int) FediverseLookup::find('@Dave' . $unique . '@Example.Social') -> userId);
        $this -> assertSame($user_id, (int) FediverseLookup::find('DAVE' . $unique . '@EXAMPLE.SOCIAL') -> userId);
    }

    /**
     * The query has to be the handle and nothing else. Somebody searching for
     * words that happen to contain an address is searching for words, and must
     * not send this server off to fetch a stranger.
     */
    public function testAHandleInsideASentenceIsNotALookup(): void
    {
        $unique = bin2hex(random_bytes(4));
        $handle = 'carol' . $unique . '@example.social';
        $this -> remoteAccount($handle, 'https://example.social/users/carol' . $unique);

        $this -> assertNull(FediverseLookup::find('has anyone seen ' . $handle . ' lately'));
        $this -> assertNull(FediverseLookup::find($handle . ' and ' . $handle));
    }

    /** Ordinary searches are ordinary searches and reach nobody. */
    public function testWhatIsNotAHandleIsNotLookedUp(): void
    {
        $this -> assertNull(FediverseLookup::find(''));
        $this -> assertNull(FediverseLookup::find('alice'));
        $this -> assertNull(FediverseLookup::find('photography'));
        // No real TLD behind it, so it never was a handle.
        $this -> assertNull(FediverseLookup::find('@alice@intranet'));
        $this -> assertNull(FediverseLookup::find('someone@example.notarealtld'));
    }

    /**
     * Nothing answers under test, so the handle resolves to nobody - and that
     * has to be an ordinary empty result, since it sits alongside a search
     * whose own results are perfectly good.
     */
    public function testAHandleNobodyAnswersToComesBackEmptyRatherThanFailing(): void
    {
        $this -> assertNull(FediverseLookup::find('@nobody' . bin2hex(random_bytes(4)) . '@example.social'));
    }
}
