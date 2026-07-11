<?php

declare(strict_types=1);

// Deletes the content a report is about (a post - and its reply subtree - or a
// message). Distinct from the post owner's DeleteButton so the owner-only
// delete handler doesn't fire on it; carries the reportId, and the endpoint
// resolves what to delete from that report.
class DeleteContentButton extends Button
{
    public function __construct(int $report_id, string $label)
    {
        parent::__construct();

        $this -> type = 'button';
        $this -> class = 'Btn DeleteReportedContentButton';
        $this -> attributes['data-report-id'] = (string) $report_id;
        $this -> contents[] = $label;
    }
}
