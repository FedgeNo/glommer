<?php

declare(strict_types=1);

/**
 * The moderation list of standing trending-entity bans, each with an Unban
 * control.
 */
class BannedTrendingEntitiesList extends ItemList
{
    public ?string $class = 'd-flex flex-column BannedTrendingEntitiesList';

    protected string $emptyNotice = 'No banned trending entities.';

    protected function rows(): array
    {
        return DB::rows('
SELECT `BannedTrendingEntities`.`type`, `BannedTrendingEntities`.`title`, `BannedTrendingEntities`.`reason`, `BannedTrendingEntities`.`createdAt`, `Users`.`slug` AS `bannedByUsername`
    FROM `BannedTrendingEntities`
    JOIN `Users` ON `Users`.`userId` = `BannedTrendingEntities`.`bannedBy`
    ORDER BY `BannedTrendingEntities`.`createdAt` DESC
    LIMIT ? OFFSET ?
', 'BannedTrendingEntity', 'ii', static::PAGE_SIZE + 1, $this -> offset);
    }
}
