<?php

declare(strict_types=1);

/**
 * The moderation list of standing trending-entity bans, each with an Unban
 * control.
 */
class BannedTrendingEntityList extends ItemList
{
    public ?string $class = 'BannedTrendingEntityList';
    public array $mixins = ['d-flex', 'flex-column'];

    protected function rows(): array
    {
        $this -> emptyNotice = (string) (Strings::for(self::class)['emptyNotice'] ?? '');

        return DB::rows('
SELECT `BannedTrendingEntities`.`type`, `BannedTrendingEntities`.`title`, `BannedTrendingEntities`.`reason`, `BannedTrendingEntities`.`createdAt`, `Users`.`slug` AS `bannedByUsername`
    FROM `BannedTrendingEntities`
    JOIN `Users` ON `Users`.`userId` = `BannedTrendingEntities`.`bannedBy`
    ORDER BY `BannedTrendingEntities`.`createdAt` DESC
    LIMIT ? OFFSET ?
', 'BannedTrendingEntity', 'ii', static::PAGE_SIZE + 1, $this -> offset);
    }
}
