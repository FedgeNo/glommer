<?php

declare(strict_types=1);

// Clears a report from the moderation queue by deleting it. Present on every
// report - the always-available way to resolve one when no ban or content
// deletion is warranted.
class DismissReportButton extends Button
{
    public function __construct(int $report_id)
    {
        parent::__construct();

        $this -> type = 'button';
        $this -> class = 'Button DismissReportButton';
        $this -> attributes['data-report-id'] = (string) $report_id;
        $this -> contents[] = 'Dismiss';
    }
}
