<?php

declare(strict_types=1);

/**
 * The moderation list of servers this instance refuses to deal with, each with
 * the control to lift it.
 */
class BlockedServerList extends ItemList
{
    public ?string $class = 'BlockedServerList';

    protected function rows(): array
    {
        $this -> emptyNotice = (string) (Strings::for(self::class)['emptyNotice'] ?? '');

        // LEFT JOIN: a block outlives the moderator who made it, and the row
        // still has to show.
        return DB::rows('
SELECT `BlockedServers`.`domain`, `BlockedServers`.`reason`, `BlockedServers`.`createdAt`, `Users`.`slug` AS `blockedByUsername`
    FROM `BlockedServers`
    LEFT JOIN `Users` ON `Users`.`userId` = `BlockedServers`.`blockedBy`
    ORDER BY `BlockedServers`.`createdAt` DESC
    LIMIT ? OFFSET ?
', 'BlockedServerCard', 'ii', static::PAGE_SIZE + 1, $this -> offset);
    }
}
