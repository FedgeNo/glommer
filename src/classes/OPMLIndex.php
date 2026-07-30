<?php

declare(strict_types=1);

/**
 * The top-level OPML directory: one outline per shard of local members,
 * pointing at that shard's own OPML file rather than listing every member
 * directly - the index itself always stays small no matter how large the
 * membership grows. There's only ever going to be one shard for a long time,
 * but the structure doesn't need to change when that stops being true.
 *
 * Remote (Fediverse) accounts and banned accounts are excluded, the same
 * policy as every other public feed.
 */
class OPMLIndex extends OPMLDocument
{
    public const SHARD_SIZE = 10000;
    public const MAX_SHARDS = 10000;

    protected function title(): string
    {
        return (string) Config::get('siteTitle');
    }

    /** @return OPMLOutline[] */
    protected function outlines(): array
    {
        $not_banned = 0;

        $result = mysqli_stmt_get_result(DB::run('
SELECT COUNT(*) AS `count`
    FROM `Users`
    WHERE `remoteActorURI` IS NULL AND `banned` = ?
', 'i', $not_banned));

        $total = (int) mysqli_fetch_assoc($result)['count'];
        $shard_count = min(self::MAX_SHARDS, max(1, (int) ceil($total / self::SHARD_SIZE)));

        $outlines = [];

        for ($shard = 1; $shard <= $shard_count; $shard++) {
            $outline = new OPMLOutline();
            $outline -> attributes['text'] = 'Members ' . $shard;
            $outline -> attributes['type'] = 'link';
            $outline -> attributes['url'] = ServerURL::absolute('/feeds/' . $shard . '.opml');
            $outlines[] = $outline;
        }

        return $outlines;
    }
}
