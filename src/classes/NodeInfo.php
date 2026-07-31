<?php

declare(strict_types=1);

/**
 * The numbers NodeInfo publishes about this server.
 *
 * Both counts are of local things only. A shadow row exists so a remote account
 * can be followed and displayed, but that person is not a member here and
 * counting them would inflate this server at every other server's expense; the
 * same goes for posts that arrived from elsewhere.
 */
class NodeInfo
{
    /** Members, not counting shadow rows for remote accounts or banned people. */
    public static function memberCount(): int
    {
        $row = DB::row('
SELECT COUNT(*) AS `total`
    FROM `Users`
    WHERE `remoteActorURI` IS NULL AND `banned` = 0
', 'PostCountData');

        return $row === null ? 0 : (int) $row -> total;
    }

    /** Posts written here, not ones federated in. */
    public static function localPostCount(): int
    {
        $row = DB::row('
SELECT COUNT(*) AS `total`
    FROM `Posts`
    WHERE `remoteObjectURI` IS NULL
', 'PostCountData');

        return $row === null ? 0 : (int) $row -> total;
    }
}
