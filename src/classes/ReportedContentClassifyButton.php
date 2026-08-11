<?php

declare(strict_types=1);

// Marks the media on a reported post as sensitive, so it sits behind a cover
// instead of being shown unasked. The proportionate answer to a report about
// something startling rather than something forbidden - the post stays up, and
// looking at it becomes a choice. Carries the reportId; the endpoint resolves
// which post that is, the same as ReportedContentDeleteButton.
class ReportedContentClassifyButton extends ButtonButton
{
    public function __construct(int $report_id)
    {
        parent::__construct();

        $this -> type = 'button';
        $this -> attributes['data-report-id'] = (string) $report_id;
        $this -> contents[] = (string) (Strings::for(self::class)['name'] ?? '');
    }
}
