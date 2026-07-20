<?php

declare(strict_types=1);

/**
 * The admin moderation queue, newest report first, grown by infinite scroll
 * (main.js) off the data-* attributes here.
 */
class ReportList extends ItemList
{
    public ?string $class = 'ReportList d-flex flex-column';

    protected string $emptyNotice = 'No reports.';

    protected function rows(): array
    {
        $rows = DB::rows('
SELECT `r`.*, `u`.`slug` AS `reporterUsername`
    FROM `Reports` `r`
    JOIN `Users` `u` ON `u`.`userId` = `r`.`reporterId`
    ORDER BY `r`.`reportId` DESC
    LIMIT ? OFFSET ?
', 'ReportData', 'ii', static::PAGE_SIZE + 1, $this -> offset);

        return array_map(static fn (ReportData $row): ReportCard => ReportCard::fromRow($row), $rows);
    }
}
