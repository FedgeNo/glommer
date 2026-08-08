<?php

declare(strict_types=1);

/**
 * The numbers on the admin's Services panel.
 *
 * What matters is that each counts what it says. A dashboard figure is
 * believed without being checked - that is the whole reason for showing one -
 * so a count that quietly includes the thousands of shadow accounts a relay
 * mints would misinform the one person who acts on it.
 */
class SiteCountersTest extends DatabaseTestCase
{
    private function markup(): string
    {
        (new \ReflectionProperty(HTMLObject::class, 'document')) -> setValue(null, new \DOMDocument());

        $element = new SiteCounters() -> toDOM();
        HTMLObject::currentDocument() -> appendChild($element);

        return (string) HTMLObject::currentDocument() -> saveHTML($element);
    }

    /** @return array<string, int> every figure the panel is currently showing */
    private function counts(): array
    {
        $method = new \ReflectionMethod(SiteCounters::class, 'counts');

        return array_map(static fn ($value): int => (int) $value, get_object_vars($method -> invoke(null)));
    }

    private function remoteAccount(): int
    {
        $unique = bin2hex(random_bytes(6));

        DB::run('
INSERT INTO `Users` (`slug`, `email`, `passwordHash`, `remoteActorURI`, `verified`)
    VALUES (?, ?, ?, ?, ?)
', 'ssssi', 'someone-' . $unique . '@remote.example', 'remote+' . $unique . '@glommer.test.invalid', '',
            'https://remote.example/users/' . $unique, 1);

        return (int) mysqli_insert_id(DB::connection());
    }

    /**
     * A relay mints a shadow account per author it carries. Counting those as
     * members would tell the admin their eight-person site has thousands.
     */
    public function testMembersAreThePeopleWhoSignedUpHere(): void
    {
        $before = $this -> counts()['members'];

        $this -> remoteAccount();

        $this -> assertSame($before, $this -> counts()['members'], 'a Fediverse account is not a member');

        self::createUser();

        $this -> assertSame($before + 1, $this -> counts()['members']);
    }

    /** Likewise the posts: what was written here, not what arrived here. */
    public function testPostsAreTheOnesWrittenHere(): void
    {
        $before = $this -> counts()['posts'];

        DB::run('
INSERT INTO `Posts` (`userId`, `description`, `remoteObjectURI`)
    VALUES (?, ?, ?)
', 'iss', $this -> remoteAccount(), 'from elsewhere', 'https://remote.example/notes/' . bin2hex(random_bytes(6)));

        $this -> assertSame($before, $this -> counts()['posts'], 'a post from another server was not written here');

        DB::run('
INSERT INTO `Posts` (`userId`, `description`)
    VALUES (?, ?)
', 'is', self::createUser(), 'written here');

        $this -> assertSame($before + 1, $this -> counts()['posts']);
    }

    /**
     * "Posted recently" is the closest honest thing to an active member, since
     * nothing records a visit - and it counts people, not posts, so somebody
     * chatty is still one of them.
     */
    public function testSomebodyPostingTwiceIsStillOneMemberPosting(): void
    {
        $before = $this -> counts()['postedThisWeek'];
        $author = self::createUser();

        foreach (['one', 'two', 'three'] as $words) {
            DB::run('
INSERT INTO `Posts` (`userId`, `description`)
    VALUES (?, ?)
', 'is', $author, $words);
        }

        $this -> assertSame($before + 1, $this -> counts()['postedThisWeek']);
    }

    /** Something older than the window is not in it. */
    public function testAnOldPostIsNotThisWeeksNews(): void
    {
        $before = $this -> counts()['postsThisWeek'];

        DB::run('
INSERT INTO `Posts` (`userId`, `description`, `createdAt`)
    VALUES (?, ?, ?)
', 'iss', self::createUser(), 'last month', date('Y-m-d H:i:s', time() - 30 * 86400));

        $this -> assertSame($before, $this -> counts()['postsThisWeek']);
    }

    /** It says what it counts, in words, rather than leaving a bare number. */
    public function testEachFigureSaysWhatItCounts(): void
    {
        $markup = $this -> markup();

        foreach (['Members:', 'posted in the last', 'Posts written here:', 'Federation deliveries waiting:'] as $label) {
            $this -> assertTrue(str_contains($markup, $label), 'missing: ' . $label);
        }
    }
}
