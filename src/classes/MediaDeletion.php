<?php

declare(strict_types=1);

/** Public media cleanup survives a committed deletion and an interrupted request. */
class MediaDeletion
{
    public static function enqueue(int $item_id, string $type): void
    {
        DB::requireTransaction();
        DB::run('INSERT INTO `MediaDeletions` (`itemId`, `type`) VALUES (?, ?)', 'is', $item_id, $type);
        DB::afterCommit(static fn () => self::process($item_id));
    }

    public static function process(int $item_id): void
    {
        DB::transaction(static function () use ($item_id): void {
            $item = DB::row('
SELECT `itemId`, `type` FROM `MediaDeletions`
    WHERE `itemId` = ? AND `nextAttemptAt` <= NOW()
    FOR UPDATE SKIP LOCKED
', \stdClass::class, 'i', $item_id);
            if ($item === null) {
                return;
            }
            // Item IDs are never reused. Refuse cleanup if a live row nevertheless exists.
            $live = DB::row('SELECT `itemId` FROM `FeedItems` WHERE `itemId` = ?', \stdClass::class, 'i', $item_id);
            if ($live === null && UploadProcessor::deleteForItem($item_id, (string) $item -> type)) {
                DB::run('DELETE FROM `MediaDeletions` WHERE `itemId` = ?', 'i', $item_id);
            } else {
                DB::run('UPDATE `MediaDeletions` SET `nextAttemptAt` = NOW() + INTERVAL 5 MINUTE WHERE `itemId` = ?', 'i', $item_id);
            }
        });
    }

    public static function drain(): void
    {
        foreach (DB::rows('SELECT `itemId` FROM `MediaDeletions` WHERE `nextAttemptAt` <= NOW() ORDER BY `nextAttemptAt`, `itemId` LIMIT 50', \stdClass::class) as $item) {
            self::process((int) $item -> itemId);
        }
    }
}
