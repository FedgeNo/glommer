<?php

declare(strict_types=1);

/**
 * The moderation list of servers this instance refuses to deal with, each with
 * the control to lift it.
 */
class BlockedDomainList extends ItemList
{
    public ?string $class = 'BlockedDomainList';
    public array $mixins = ['d-flex', 'flex-column'];

    protected string $emptyNotice = 'No blocked servers.';

    protected function rows(): array
    {
        // LEFT JOIN: a block outlives the moderator who made it, and the row
        // still has to show.
        return DB::rows('
SELECT `BlockedDomains`.`domain`, `BlockedDomains`.`reason`, `BlockedDomains`.`createdAt`, `Users`.`slug` AS `blockedByUsername`
    FROM `BlockedDomains`
    LEFT JOIN `Users` ON `Users`.`userId` = `BlockedDomains`.`blockedBy`
    ORDER BY `BlockedDomains`.`createdAt` DESC
    LIMIT ? OFFSET ?
', 'BlockedDomainCard', 'ii', static::PAGE_SIZE + 1, $this -> offset);
    }
}
