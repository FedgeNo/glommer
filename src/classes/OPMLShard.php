<?php

declare(strict_types=1);

/**
 * One page of the member directory: up to OPMLIndex::SHARD_SIZE local
 * members as flat "rss" outlines pointing at their own feed - a feed reader
 * can subscribe to everyone in the shard at once, and a crawler reaches every
 * member without waiting for their posts to roll through a windowed feed.
 */
class OPMLShard extends OPMLDocument
{
    public int $shard = 1;

    protected function title(): string
    {
        return (string) Config::get('siteTitle') . ' - Members ' . max(1, $this -> shard);
    }

    /** @return OPMLOutline[] */
    protected function outlines(): array
    {
        $not_banned = 0;
        $offset = (max(1, $this -> shard) - 1) * OPMLIndex::SHARD_SIZE;

        $users = DB::rows('
SELECT `slug`, `title`
    FROM `Users`
    WHERE `remoteActorURI` IS NULL AND `banned` = ?
    ORDER BY `userId` ASC
    LIMIT ? OFFSET ?
', 'User', 'iii', $not_banned, OPMLIndex::SHARD_SIZE, $offset);

        $outlines = [];

        foreach ($users as $user) {
            $outline = new OPMLOutline();
            $outline -> attributes['text'] = $user -> title ?: $user -> slug;
            $outline -> attributes['type'] = 'rss';
            $outline -> attributes['xmlUrl'] = ServerURL::absolute('/users/' . $user -> slug . '/feed.xml');
            $outline -> attributes['htmlUrl'] = ServerURL::absolute('/users/' . $user -> slug . '/');
            $outlines[] = $outline;
        }

        return $outlines;
    }
}
