<?php

declare(strict_types=1);

class MapPostList
{
    /** @var MapPost[] */
    public array $items = [];

    public function __construct(bool $include_remote)
    {
        $local_only = $include_remote ? '' : ' AND `p`.`remoteObjectURI` IS NULL';

        $this -> items = DB::rows('
SELECT `l`.`postId`, `l`.`latitude`, `l`.`longitude`, `p`.`title`, `p`.`createdAt`, `u`.`slug`, `u`.`title` AS `authorName`
    FROM `PostLocations` `l`
    JOIN `Posts` `p` ON `p`.`postId` = `l`.`postId`
    JOIN `Users` `u` ON `u`.`userId` = `p`.`userId`
    WHERE `u`.`banned` = ?' . $local_only . '
    ORDER BY `l`.`postId` DESC
    LIMIT 1000
', MapPost::class, 'i', 0);
    }
}
