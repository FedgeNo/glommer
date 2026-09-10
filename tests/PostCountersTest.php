<?php

declare(strict_types=1);

/** Stored totals follow the records, including retries and FK cascades. */
class PostCountersTest extends DatabaseTestCase
{
    private static function post(int $user_id): int
    {
        DB::run('
INSERT INTO `Posts` (`userId`)
    VALUES (?)
', 'i', $user_id);

        return (int) mysqli_insert_id(DB::connection());
    }

    private static function reply(int $user_id, int $parent_id, ?string $uri = null): ?int
    {
        // Exercise the actual inbound write, including its duplicate-URI path.
        return (new \ReflectionMethod(ActivityPubInbox::class, 'storeNote')) -> invoke(
            null, [], $uri ?? 'https://counters.example/notes/' . bin2hex(random_bytes(8)),
            User::load($user_id), $parent_id
        );
    }

    private static function counts(int $post_id): array
    {
        $post = DB::row('
SELECT `replyCount`, `likeCount`, `repostCount`
    FROM `Posts`
    WHERE `postId` = ?
', 'Post', 'i', $post_id);

        return [$post -> replyCount, $post -> likeCount, $post -> repostCount];
    }

    public function testLikesKeepTheirRecordsAndCountEachMemberOnce(): void
    {
        $author = self::createUser();
        $first = self::createUser();
        $second = self::createUser();

        try {
            $post = self::post($author);
            $this -> assertSame([0, 0, 0], self::counts($post));
            $this -> assertTrue(Like::create($first, $post));
            $this -> assertFalse(Like::create($first, $post));
            $this -> assertTrue(Like::create($second, $post));
            $this -> assertSame([0, 2, 0], self::counts($post));
            $this -> assertTrue(Like::exists($first, $post));
            $this -> assertTrue(Like::exists($second, $post));

            Like::remove($first, $post);
            Like::remove($first, $post);
            $this -> assertSame([0, 1, 0], self::counts($post));
            $this -> assertFalse(Like::exists($first, $post));
            $this -> assertTrue(Like::exists($second, $post));

            Like::remove($second, $post);
            $this -> assertSame([0, 0, 0], self::counts($post));
        } finally {
            User::delete($author);
            User::delete($first);
            User::delete($second);
        }
    }

    public function testRepostRetriesAndChangedActivityIdsDoNotAddToTheTotal(): void
    {
        $author = self::createUser();
        $member = self::createUser();

        try {
            $post = self::post($author);
            $this -> assertTrue(Repost::create($member, $post));
            Repost::create($member, $post);
            Repost::record($member, $post, 'https://counters.example/activities/changed');
            Repost::record($member, $post, 'https://counters.example/activities/changed');
            $this -> assertSame([0, 0, 1], self::counts($post));
            $this -> assertTrue(Repost::exists($member, $post));
            $this -> assertSame([$post => true], Repost::repostedForPosts([$post], $member));
            $this -> assertSame([], Repost::repostedForPosts([$post], null));

            Repost::remove($member, $post);
            Repost::remove($member, $post);
            $this -> assertSame([0, 0, 0], self::counts($post));
            $this -> assertFalse(Repost::exists($member, $post));
        } finally {
            User::delete($author);
            User::delete($member);
        }
    }

    public function testDuplicateReplyRollsBackItsIncrement(): void
    {
        $author = self::createUser();

        try {
            $post = self::post($author);
            $uri = 'https://counters.example/notes/' . bin2hex(random_bytes(8));
            $reply = self::reply($author, $post, $uri);
            $this -> assertNotNull($reply);
            $this -> assertNull(self::reply($author, $post, $uri));
            $this -> assertSame([1, 0, 0], self::counts($post));
            $this -> assertSame([0, 0, 0], self::counts($reply));
        } finally {
            User::delete($author);
        }
    }

    public function testConcurrentRetriesKeepCountsEqualToTheRecords(): void
    {
        $author = self::createUser();
        $members = [self::createUser(), self::createUser(), self::createUser()];
        $class_dir = dirname(__DIR__) . '/src/classes/';
        $script = '
spl_autoload_register(static function (string $class): void {
    require $GLOBALS["argv"][1] . $class . ".php";
});
$post_id = (int) $argv[2];
foreach (array_slice($argv, 4) as $member) {
    $member = (int) $member;
    if ($argv[3] === "add") {
        Like::create($member, $post_id);
        Repost::record($member, $post_id, "https://counters.example/activities/" . $member);
    } else {
        Like::remove($member, $post_id);
        Repost::remove($member, $post_id);
    }
}
';

        try {
            $post = self::post($author);

            foreach (['add', 'remove'] as $action) {
                $processes = [];

                // Each connection delivers the same members' actions. Inserts
                // and deletes must affect the total only for the winning call.
                for ($worker = 0; $worker < 4; $worker++) {
                    $process = proc_open([
                        PHP_BINARY, '-r', $script, $class_dir, (string) $post, $action,
                        ...array_map('strval', $members),
                    ], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                    $this -> assertTrue(is_resource($process));
                    fclose($pipes[0]);
                    $processes[] = [$process, $pipes];
                }

                $results = [];

                foreach ($processes as [$process, $pipes]) {
                    $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
                    fclose($pipes[1]);
                    fclose($pipes[2]);
                    $results[] = [proc_close($process), $output];
                }

                foreach ($results as [$exit_code, $output]) {
                    $this -> assertSame(0, $exit_code, $output);
                }

                $count = $action === 'add' ? count($members) : 0;
                $this -> assertSame([0, $count, $count], self::counts($post));

                foreach ($members as $member) {
                    $this -> assertSame($action === 'add', Like::exists($member, $post));
                    $this -> assertSame($action === 'add', Repost::exists($member, $post));
                }
            }
        } finally {
            User::delete($author);

            foreach ($members as $member) {
                User::delete($member);
            }
        }
    }

    public function testDeletingAReplyTreeOnlySubtractsItsDirectContribution(): void
    {
        $author = self::createUser();

        try {
            $post = self::post($author);
            $reply = self::reply($author, $post);
            $sibling = self::reply($author, $post);
            $nested = self::reply($author, $reply);
            $this -> assertSame([2, 0, 0], self::counts($post));
            $this -> assertSame([1, 0, 0], self::counts($reply));

            Post::delete($nested);
            $this -> assertSame([0, 0, 0], self::counts($reply));
            $this -> assertSame([2, 0, 0], self::counts($post));

            $nested = self::reply($author, $reply);
            Post::delete($reply);
            Post::delete($reply);
            $this -> assertSame([1, 0, 0], self::counts($post));
            $this -> assertNull(DB::row('SELECT `postId` FROM `Posts` WHERE `postId` = ?', 'Post', 'i', $nested));
            $this -> assertSame([0, 0, 0], self::counts($sibling));
        } finally {
            User::delete($author);
        }
    }

    public function testDeletingAnAccountRemovesItsContributionsToSurvivingPosts(): void
    {
        $author = self::createUser();
        $leaving = self::createUser();
        $staying = self::createUser();

        try {
            $post = self::post($author);
            $reply = self::reply($leaving, $post);
            self::reply($leaving, $reply);
            $other_authors_child = self::reply($staying, $reply);
            self::reply($staying, $post);
            Like::create($leaving, $post);
            Like::create($staying, $post);
            Repost::record($leaving, $post, 'https://counters.example/activities/leaving');
            Repost::record($staying, $post, 'https://counters.example/activities/staying');
            $this -> assertSame([2, 2, 2], self::counts($post));

            User::delete($leaving);
            User::delete($leaving);
            $this -> assertSame([1, 1, 1], self::counts($post));
            $this -> assertNull(DB::row('SELECT `postId` FROM `Posts` WHERE `postId` = ?', 'Post', 'i', $other_authors_child));
            $this -> assertTrue(Like::exists($staying, $post));
            $this -> assertTrue(Repost::exists($staying, $post));

            User::delete($staying);
            $this -> assertSame([0, 0, 0], self::counts($post));
        } finally {
            User::delete($author);
            User::delete($leaving);
            User::delete($staying);
        }
    }

    public function testInstallerBackfillsAndRepairsTotalsFromRetainedRecords(): void
    {
        $author = self::createUser();
        $member = self::createUser();

        try {
            $post = self::post($author);
            $empty = self::post($author);
            $reply = self::reply($member, $post);
            self::reply($member, $reply);
            Like::create($member, $post);
            Repost::record($member, $post, 'https://counters.example/activities/backfill');

            DB::run('
UPDATE `Posts`
    SET `replyCount` = 99, `likeCount` = 99, `repostCount` = 99
    WHERE `userId` IN (?, ?)
', 'ii', $author, $member);

            for ($pass = 0; $pass < 2; $pass++) {
                SchemaInstaller::runMaintenance(DB::connection());
                $this -> assertSame([1, 1, 1], self::counts($post));
                $this -> assertSame([1, 0, 0], self::counts($reply));
                $this -> assertSame([0, 0, 0], self::counts($empty));
                $this -> assertTrue(Like::exists($member, $post));
                $this -> assertTrue(Repost::exists($member, $post));
            }
        } finally {
            User::delete($author);
            User::delete($member);
        }
    }

    public function testFeedHydrationAndPayloadUseTheStoredTotals(): void
    {
        $author = self::createUser();
        $member = self::createUser();
        $previous_session = $_SESSION ?? [];
        $_SESSION['userId'] = $member;
        Auth::clearUserCache();

        try {
            $post = self::post($author);
            Like::create($member, $post);
            Repost::record($member, $post, 'https://counters.example/activities/feed');

            // Deliberately distinguish the stored values from the records to
            // catch a loader or payload silently replacing them with COUNTs.
            DB::run('
UPDATE `Posts`
    SET `replyCount` = 12, `likeCount` = 34, `repostCount` = 56
    WHERE `postId` = ?
', 'i', $post);

            $rows = new ProfileFeedList(['userId' => $author]) -> items;
            $this -> assertCount(1, $rows);
            $loaded = $rows[0];
            $this -> assertSame(12, $loaded -> replyCount);
            $this -> assertSame(34, $loaded -> likeCount);
            $this -> assertSame(56, $loaded -> repostCount);
            $this -> assertTrue($loaded -> liked);
            $this -> assertTrue($loaded -> reposted);
            $payload = $loaded -> toPayload((bool) $loaded -> liked, (bool) $loaded -> bookmarked);
            $this -> assertSame(12, $payload['replyCount']);
            $this -> assertSame(34, $payload['likeCount']);
            $this -> assertSame(56, $payload['repostCount']);
        } finally {
            $_SESSION = $previous_session;
            Auth::clearUserCache();
            User::delete($author);
            User::delete($member);
        }
    }
}
