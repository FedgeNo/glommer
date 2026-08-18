<?php

declare(strict_types=1);

/**
 * The admin moderation queue, newest report first, grown by infinite scroll
 * (main.js) off the data-* attributes here.
 */
class ReportList extends ItemList
{
    public ?string $class = 'ReportList';

    protected function rows(): array
    {
        // Read here, not as the property's default (which cannot call a
        // function) and not in toDOM() (which, like every List, this class
        // does not define - ItemList's shared one reads emptyNotice as a
        // plain property already set by the time it runs).
        $this -> emptyNotice = (string) (Strings::for(self::class)['emptyNotice'] ?? '');

        return DB::rows('
SELECT `r`.*, `u`.`slug` AS `reporterUsername`
    FROM `Reports` `r`
    JOIN `Users` `u` ON `u`.`userId` = `r`.`reporterId`
    ORDER BY `r`.`reportId` DESC
    LIMIT ? OFFSET ?
', Report::class, 'ii', static::PAGE_SIZE + 1, $this -> offset);
    }

    /**
     * @return array<string, string>
     */
    protected function dataAttributes(): array
    {
        return ['data-infinite-scroll' => (string) json_encode([
            'endpoint' => '/api/report-history',
            'itemType' => 'Report',
        ])];
    }
}
