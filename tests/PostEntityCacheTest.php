<?php

declare(strict_types=1);

/** A deterministic extractor makes cache reuse and races observable without a model. */
class CountingPostEntityCache extends PostEntityCache
{
    public static array $batches = [];
    public static string $revision = 'initial';
    public static bool $succeeds = true;
    public static ?\Closure $duringExtraction = null;

    protected static function version(): string
    {
        return hash('sha256', self::$revision);
    }

    protected static function extract(array $deltas): array
    {
        self::$batches[] = count($deltas);

        if (self::$duringExtraction !== null) {
            $callback = self::$duringExtraction;
            self::$duringExtraction = null;
            $callback();
        }

        return [
            'entities' => EntityExtractor::extractBatch($deltas, false),
            'languages' => array_map(static fn (?string $delta): ?string => $delta === null ? null : 'en', $deltas),
            'complete' => self::$succeeds,
        ];
    }

    public static function reset(): void
    {
        self::$batches = [];
        self::$revision = 'initial';
        self::$succeeds = true;
        self::$duringExtraction = null;
    }
}

class PostEntityCacheTest extends DatabaseTestCase
{
    private static function post(int $author, ?string $text): Post
    {
        DB::run('INSERT INTO `Posts` (`userId`, `descriptionDelta`) VALUES (?, ?)', 'is', $author,
            $text === null ? null : json_encode([['insert' => $text . "\n"]]));

        return self::load((int) mysqli_insert_id(DB::connection()));
    }

    private static function load(int $post): Post
    {
        return DB::row('SELECT * FROM `Posts` WHERE `postId` = ?', 'Post', 'i', $post);
    }

    public function testUnchangedResultsAreReusedAndEditsAndVersionsInvalidateThem(): void
    {
        $author = self::createUser();
        CountingPostEntityCache::reset();

        try {
            $a = self::post($author, '#alpha');
            $b = self::post($author, '#bravo');
            $first = CountingPostEntityCache::forPosts([$a, $b], true);
            $this -> assertSame([2], CountingPostEntityCache::$batches);
            $this -> assertSame($first, CountingPostEntityCache::forPosts([$a, $b], true));
            $this -> assertSame([2], CountingPostEntityCache::$batches);

            // Installer language repair can clear the column while the
            // extraction remains valid. Recover it without a second model run.
            DB::run('UPDATE `Posts` SET `detectedLanguage` = NULL WHERE `postId` = ?', 'i', $a -> postId);
            CountingPostEntityCache::forPosts([self::load($a -> postId)], true);
            $this -> assertSame('en', self::load($a -> postId) -> detectedLanguage);
            $this -> assertSame([2], CountingPostEntityCache::$batches);

            DB::run('UPDATE `Posts` SET `descriptionDelta` = ? WHERE `postId` = ?', 'si', json_encode([['insert' => "#charlie\n"]]), $a -> postId);
            $a = self::load($a -> postId);
            $updated = CountingPostEntityCache::forPosts([$a, $b], true);
            $this -> assertSame([2, 1], CountingPostEntityCache::$batches);
            $this -> assertSame([['type' => 'hashtag', 'value' => 'charlie']], $updated[0]);
            $this -> assertSame($first[1], $updated[1]);

            CountingPostEntityCache::$revision = 'new extractor';
            CountingPostEntityCache::forPosts([$a, $b], true);
            $this -> assertSame([2, 1, 2], CountingPostEntityCache::$batches);
        } finally {
            DB::run('DELETE FROM `Users` WHERE `userId` = ?', 'i', $author);
        }
    }

    public function testReadsNeverStartExtractionAndEmptyResultsAreRemembered(): void
    {
        $author = self::createUser();
        CountingPostEntityCache::reset();

        try {
            $post = self::post($author, null);
            $this -> assertSame([[]], CountingPostEntityCache::forPosts([$post]));
            $this -> assertSame([], CountingPostEntityCache::$batches);
            $this -> assertNull(DB::row('SELECT `postId` FROM `PostEntityExtractions` WHERE `postId` = ?', 'stdClass', 'i', $post -> postId));
            $this -> assertSame([[]], CountingPostEntityCache::forPosts([$post], true));
            $this -> assertSame([[]], CountingPostEntityCache::forPosts([$post], true));
            $this -> assertSame([1], CountingPostEntityCache::$batches);
        } finally {
            DB::run('DELETE FROM `Users` WHERE `userId` = ?', 'i', $author);
        }
    }

    public function testFailedExtractionRetriesAfterItsDeadline(): void
    {
        $author = self::createUser();
        CountingPostEntityCache::reset();

        try {
            $post = self::post($author, '#retry');
            CountingPostEntityCache::$succeeds = false;
            CountingPostEntityCache::forPosts([$post], true);
            CountingPostEntityCache::forPosts([$post], true);
            $this -> assertSame([1], CountingPostEntityCache::$batches);

            DB::run('UPDATE `PostEntityExtractions` SET `retryAt` = DATE_SUB(NOW(), INTERVAL 1 SECOND) WHERE `postId` = ?', 'i', $post -> postId);
            CountingPostEntityCache::$succeeds = true;
            CountingPostEntityCache::forPosts([$post], true);
            CountingPostEntityCache::forPosts([$post], true);
            $this -> assertSame([1, 1], CountingPostEntityCache::$batches);
            $this -> assertSame(1, (int) DB::row('SELECT `complete` FROM `PostEntityExtractions` WHERE `postId` = ?', 'stdClass', 'i', $post -> postId) -> complete);
        } finally {
            DB::run('DELETE FROM `Users` WHERE `userId` = ?', 'i', $author);
        }
    }

    public function testConcurrentEditsAndDeletionCannotStoreAnObsoleteExtraction(): void
    {
        $author = self::createUser();
        CountingPostEntityCache::reset();

        try {
            $post = self::post($author, '#before');
            CountingPostEntityCache::$duringExtraction = static function () use ($post): void {
                DB::run('UPDATE `Posts` SET `descriptionDelta` = ?, `detectedLanguage` = ? WHERE `postId` = ?', 'ssi',
                    json_encode([['insert' => "#after\n"]]), 'fr', $post -> postId);
            };
            CountingPostEntityCache::forPosts([$post], true);
            $this -> assertNull(DB::row('SELECT `postId` FROM `PostEntityExtractions` WHERE `postId` = ?', 'stdClass', 'i', $post -> postId));
            $this -> assertSame('fr', self::load($post -> postId) -> detectedLanguage);

            $post = self::load($post -> postId);
            $this -> assertSame([[['type' => 'hashtag', 'value' => 'after']]], CountingPostEntityCache::forPosts([$post], true));
            CountingPostEntityCache::$revision = 'force extraction';
            CountingPostEntityCache::$duringExtraction = static function () use ($post): void {
                DB::run('DELETE FROM `Posts` WHERE `postId` = ?', 'i', $post -> postId);
            };
            CountingPostEntityCache::forPosts([$post], true);
            $this -> assertNull(DB::row('SELECT `postId` FROM `PostEntityExtractions` WHERE `postId` = ?', 'stdClass', 'i', $post -> postId));
        } finally {
            CountingPostEntityCache::$duringExtraction = null;
            DB::run('DELETE FROM `Users` WHERE `userId` = ?', 'i', $author);
        }
    }

    public function testColdBackfillsUseBoundedBatches(): void
    {
        $author = self::createUser();
        CountingPostEntityCache::reset();

        try {
            $posts = [];

            for ($i = 0; $i <= PostEntityCache::BATCH_SIZE; $i++) {
                $posts[] = self::post($author, null);
            }

            CountingPostEntityCache::forPosts($posts, true);
            $this -> assertSame([PostEntityCache::BATCH_SIZE, 1], CountingPostEntityCache::$batches);
            CountingPostEntityCache::forPosts($posts, true);
            $this -> assertSame([PostEntityCache::BATCH_SIZE, 1], CountingPostEntityCache::$batches);
        } finally {
            DB::run('DELETE FROM `Users` WHERE `userId` = ?', 'i', $author);
        }
    }

    public function testCachedRankingsStillRefreshDecayAndAuthorsAndRemoveMissingTopics(): void
    {
        $authors = [self::createUser(), self::createUser(), self::createUser()];
        $tag = 'cachetrend' . bin2hex(random_bytes(6));
        CountingPostEntityCache::reset();

        try {
            $posts = array_map(static fn (int $author): Post => self::post($author, '#' . $tag), $authors);
            $posts[] = self::post($authors[0], '#' . $tag);
            CountingPostEntityCache::forPosts($posts, true);
            $ids = array_map(static fn (Post $post): int => $post -> postId, $posts);

            // Seed valid extracted results without invoking an optional model.
            DB::run('UPDATE `PostEntityExtractions` SET `extractorVersion` = ? WHERE `postId` IN (?, ?, ?, ?)',
                'siiii', EntityExtractor::cacheVersion(), ...$ids);
            EntityRanker::recompute(false);
            $first = DB::row('SELECT * FROM `Entities` WHERE `type` = ? AND `slug` = ?', 'Entity', 'ss', 'hashtag', $tag);
            $this -> assertSame(3, $first -> userCount);
            $this -> assertSame(4, $first -> postCount);
            $this -> assertSame(0, $first -> popularity, 'a page must leave new popularity contributions for the background pass');

            DB::run('UPDATE `Posts` SET `createdAt` = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 6 HOUR) WHERE `postId` IN (?, ?, ?, ?)', 'iiii', ...$ids);
            EntityRanker::recompute(false);
            $decayed = DB::row('SELECT * FROM `Entities` WHERE `entityId` = ?', 'Entity', 'i', $first -> entityId);
            $this -> assertTrue(abs($decayed -> score / $first -> score - 0.5) < 0.01);
            $this -> assertSame([4], CountingPostEntityCache::$batches);

            DB::run('UPDATE `Users` SET `banned` = 1 WHERE `userId` = ?', 'i', $authors[2]);
            // Put the old row in this second's generation, independently of
            // machine speed, to exercise repeated refreshes without sleeping.
            DB::run('UPDATE `Entities` SET `computedAt` = UTC_TIMESTAMP() WHERE `entityId` = ?', 'i', $first -> entityId);
            EntityRanker::recompute(false);
            $listed = array_map(static fn (Entity $entity): string => $entity -> slug, (new TrendingEntityList()) -> items);
            $this -> assertFalse(in_array($tag, $listed, true), 'two authors no longer qualify even when the preceding stamp matches');
            $this -> assertNotNull(DB::row('SELECT `entityId` FROM `Entities` WHERE `entityId` = ?', 'Entity', 'i', $first -> entityId));
        } finally {
            DB::run('DELETE FROM `Users` WHERE `userId` IN (?, ?, ?)', 'iii', ...$authors);
            DB::run('DELETE FROM `Entities` WHERE `type` = ? AND `slug` = ?', 'ss', 'hashtag', $tag);
        }
    }
}
