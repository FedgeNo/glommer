<?php

declare(strict_types=1);

/**
 * The admin moderation queue's list of ReportCards, paginated the same way
 * FeedList/NotificationList are: it carries a has-more flag as a data-*
 * attribute so main.js can fetch the next page from api/report-history.php
 * (by offset - how many cards are already shown) and append more on scroll.
 */
class ReportList extends ItemList
{
    public ?string $class = 'ReportList d-flex flex-column';

    public bool $hasMore = false;

    public function toDOM(): \DOMElement
    {
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

        $list -> hasMore = $has_more;

        foreach ($rows as $row) {
            $list -> addContent(ReportCard::fromRow($row));
        }

        return $list;
    }
}
