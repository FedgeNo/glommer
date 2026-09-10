<?php

declare(strict_types=1);

/** Reuse unchanged post extractions; ranking still refreshes time and author weights. */
class PostEntityCache
{
    private const RETRY_SECONDS = 600;
    public const BATCH_SIZE = 500;

    /** An explicit installer run may retry failures without waiting for the timer. */
    public static function retryFailed(): void
    {
        DB::run('UPDATE `PostEntityExtractions` SET `retryAt` = NOW() WHERE `complete` = 0');
    }

    /**
     * Only background callers enable extraction. A page can use fresh hashtags
     * for a pending post without starting Python or persisting an incomplete read.
     *
     * @param Post[] $posts
     * @return array<int, array<int, array{type: string, value: string}>> same order as $posts
     */
    public static function forPosts(array $posts, bool $extract_missing = false): array
    {
        if ($posts === []) {
            return [];
        }

        $ids = array_map(static fn (Post $post): int => (int) $post -> postId, $posts);
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $cached = [];

        foreach (DB::rows('
SELECT *
    FROM `PostEntityExtractions`
    WHERE `postId` IN (' . $placeholders . ')
', 'stdClass', str_repeat('i', count($ids)), ...$ids) as $row) {
            $cached[(int) $row -> postId] = $row;
        }

        $version = static::version();
        $results = [];
        $pending = [];

        foreach ($posts as $i => $post) {
            $hash = hash('sha256', (string) $post -> descriptionDelta);
            $row = $cached[(int) $post -> postId] ?? null;
            $entities = $row === null ? null : json_decode($row -> entities, true);
            $matches = $row !== null && $row -> contentHash === $hash
                && $row -> extractorVersion === $version && is_array($entities);

            $results[$i] = $matches ? $entities : EntityExtractor::extractBatch([$post -> descriptionDelta], false)[0];

            if ($extract_missing && $matches) {
                self::recordLanguage($post, $hash, $row -> detectedLanguage);
            }

            if ($extract_missing && (!$matches || (!(bool) $row -> complete && strtotime((string) $row -> retryAt) <= time()))) {
                $pending[$i] = ['post' => $post, 'hash' => $hash];
            }
        }

        if ($pending === []) {
            return $results;
        }

        // Bound cold backfills too: one failed batch must not lose a whole
        // window's completed work or exceed the subprocess time limit.
        foreach (array_chunk($pending, self::BATCH_SIZE, true) as $batch) {
            $extracted = static::extract(array_map(static fn (array $entry): ?string => $entry['post'] -> descriptionDelta, array_values($batch)));
            $complete = (bool) $extracted['complete'];
            $retry_at = $complete ? null : gmdate('Y-m-d H:i:s', time() + self::RETRY_SECONDS);

            foreach (array_keys($batch) as $index => $i) {
                $post = $batch[$i]['post'];
                $hash = $batch[$i]['hash'];
                $entities = $extracted['entities'][$index];
                $language = $extracted['languages'][$index] ?? null;

                // A post edited or deleted while the model was running must not
                // acquire results for the older text. The same guard protects its language.
                DB::run('
INSERT INTO `PostEntityExtractions` (`postId`, `contentHash`, `extractorVersion`, `entities`, `detectedLanguage`, `complete`, `retryAt`)
    SELECT `postId`, ?, ?, ?, ?, ?, ?
        FROM `Posts`
        WHERE `postId` = ? AND SHA2(COALESCE(`descriptionDelta`, \'\'), 256) = ?
    ON DUPLICATE KEY UPDATE `contentHash` = VALUES(`contentHash`), `extractorVersion` = VALUES(`extractorVersion`),
        `entities` = VALUES(`entities`), `detectedLanguage` = VALUES(`detectedLanguage`),
        `complete` = VALUES(`complete`), `retryAt` = VALUES(`retryAt`)
', 'ssssisis', $hash, $version, json_encode($entities, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $language, $complete ? 1 : 0, $retry_at, $post -> postId, $hash);

                self::recordLanguage($post, $hash, $language);

                $results[$i] = $entities;
            }
        }

        return $results;
    }

    /** Reuse the language too when an installer has queued it for backfill. */
    private static function recordLanguage(Post $post, string $hash, ?string $language): void
    {
        if ($language !== null && $language !== $post -> detectedLanguage) {
            DB::run('
UPDATE `Posts`
    SET `detectedLanguage` = ?
    WHERE `postId` = ? AND SHA2(COALESCE(`descriptionDelta`, \'\'), 256) = ?
', 'sis', $language, $post -> postId, $hash);
        }
    }

    protected static function version(): string
    {
        return EntityExtractor::cacheVersion();
    }

    /** @return array{entities: array, languages: array, complete: bool} */
    protected static function extract(array $deltas): array
    {
        $entities = EntityExtractor::extractBatch($deltas);

        return ['entities' => $entities, 'languages' => EntityExtractor::detectedLanguages(), 'complete' => EntityExtractor::completed()];
    }
}
