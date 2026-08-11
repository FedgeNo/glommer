<?php

declare(strict_types=1);

/**
 * The admin moderation queue, newest report first, grown by infinite scroll
 * (main.js) off the data-* attributes here.
 */
class ReportList extends ItemList
{
    public ?string $class = 'ReportList';
    public array $mixins = ['d-flex', 'flex-column'];

    protected function rows(): array
    {
        // Read here, not as the property's default (which cannot call a
        // function) and not in toDOM() (which, like every List, this class
        // does not define - ItemList's shared one reads emptyNotice as a
        // plain property already set by the time it runs).
        $this -> emptyNotice = (string) (Strings::for(self::class)['emptyNotice'] ?? '');

        $rows = DB::rows('
SELECT `r`.*, `u`.`slug` AS `reporterUsername`
    FROM `Reports` `r`
    JOIN `Users` `u` ON `u`.`userId` = `r`.`reporterId`
    ORDER BY `r`.`reportId` DESC
    LIMIT ? OFFSET ?
', 'ReportData', 'ii', static::PAGE_SIZE + 1, $this -> offset);

        return array_map(static fn (ReportData $row): ReportCard => ReportCard::fromRow($row), $rows);
    }

    /**
     * @return array<string, string>
     */
    protected function dataAttributes(): array
    {
        return ['data-infinite-scroll' => (string) json_encode([
            'endpoint' => '/api/report-history',
            'itemType' => 'ReportCard',
        ])];
    }
}

