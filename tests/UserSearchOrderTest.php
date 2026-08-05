<?php

declare(strict_types=1);

/**
 * A server subscribed to a relay holds a shadow account for every author whose
 * post has passed through it - thousands of strangers, against a membership
 * that might be eight. Search has to put this site's own people first or the
 * person being looked for is somewhere on page forty.
 *
 * Remote accounts still have to appear: finding one by handle is how you
 * follow it, and a blocked one has to stay findable to be unblocked.
 */
class UserSearchOrderTest extends DatabaseTestCase
{
    /**
     * Fixtures share a nonsense term nothing else in the shared test database
     * matches, so these assertions are about their order alone whatever else
     * the run has created.
     */
    private string $term;

    private function createLocal(string $suffix): int
    {
        $user_id = self::createUser();

        DB::run('
UPDATE `Users`
    SET `title` = ?
    WHERE `userId` = ?
', 'si', $this -> term . $suffix, $user_id);

        return $user_id;
    }

    private function createRemote(string $suffix): int
    {
        $user_id = self::createUser();
        $actor_uri = 'https://remote.example/users/' . bin2hex(random_bytes(6));

        DB::run('
UPDATE `Users`
    SET `title` = ?, `remoteActorURI` = ?
    WHERE `userId` = ?
', 'ssi', $this -> term . $suffix, $actor_uri, $user_id);

        return $user_id;
    }

    /** @return int[] the matching userIds, in the order search returns them */
    private function searchResults(): array
    {
        $list = new UserSearchList(['query' => $this -> term]);

        return array_map(static fn ($user): int => (int) $user -> userId, $list -> items);
    }

    public function testLocalMembersComeBeforeRemoteAccounts(): void
    {
        $this -> term = 'zq' . bin2hex(random_bytes(4));

        // The local member is created FIRST, so it holds the lower userId and
        // the newest-first tiebreaker would put the remote account ahead of
        // it. Only the local-first term produces this order - created the
        // other way round the test passes whether or not the fix is there.
        $local = $this -> createLocal('one');
        $remote = $this -> createRemote('two');

        $this -> assertSame([$local, $remote], $this -> searchResults());
    }

    /** A relay's worth of strangers must not push the members off the page. */
    public function testOneLocalMemberOutranksManyRemoteAccounts(): void
    {
        $this -> term = 'zq' . bin2hex(random_bytes(4));

        // Again oldest, so every one of the strangers outranks it on id.
        $local = $this -> createLocal('member');

        for ($i = 0; $i < 5; $i++) {
            $this -> createRemote('remote' . $i);
        }

        $this -> assertSame($local, $this -> searchResults()[0]);
    }

    /** Still listed, or a remote account could never be followed or unblocked. */
    public function testRemoteAccountsAreStillFound(): void
    {
        $this -> term = 'zq' . bin2hex(random_bytes(4));

        $remote = $this -> createRemote('only');

        $this -> assertSame([$remote], $this -> searchResults());
    }

    /**
     * Within the local members, a name match still beats a bio-only one - the
     * new term leads the ordering, it does not replace what was there.
     */
    public function testANameMatchStillBeatsABioMatch(): void
    {
        $this -> term = 'zq' . bin2hex(random_bytes(4));

        // Oldest again, so the id tiebreaker alone would rank it last.
        $name_match = $this -> createLocal('named');

        $bio_match = self::createUser();
        DB::run('
UPDATE `Users`
    SET `description` = ?
    WHERE `userId` = ?
', 'si', 'writes about ' . $this -> term . ' at length', $bio_match);

        $this -> assertSame([$name_match, $bio_match], $this -> searchResults());
    }
}
