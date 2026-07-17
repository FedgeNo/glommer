<?php

declare(strict_types=1);

/**
 * The admin moderation queue's list of ReportCards, cursor-paginated the same
 * way FeedList/NotificationList are: it carries the oldest report id shown and
 * a has-more flag as data-* attributes so main.js can fetch the next page from
 * api/report-history.php and append more cards on scroll.
 */
class ReportList extends ItemList
{
    public ?string $class = 'ReportList d-flex flex-column';

    public ?int $oldestReportId = null;
    public bool $hasMore = false;

    public function toDOM(): \DOMElement
    {
        if ($this -> oldestReportId !== null) {
            $this -> attributes['data-oldest-report-id'] = (string) $this -> oldestReportId;
        }

        $this -> attributes['data-has-more'] = $this -> hasMore ? '1' : '0';

        return parent::toDOM();
    }

    /**
     * @param ReportData[] $rows newest first.
     */
    public static function fromRows(array $rows, bool $has_more): self
    {
        $list = new self();

        if ($rows === []) {
            $list -> addContent(new Notice('No reports.'));

            return $list;
        }

        $list -> oldestReportId = (int) $rows[count($rows) - 1] -> reportId;
        $list -> hasMore = $has_more;

        foreach ($rows as $row) {
            $list -> addContent(ReportCard::fromRow($row));
        }

        return $list;
    }
}
