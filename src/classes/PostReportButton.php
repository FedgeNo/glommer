<?php

declare(strict_types=1);

/**
 * Reports a post. A subclass rather than a ReportButton built with 'post',
 * because the three things that can be reported are three different buttons in
 * the markup even though they share a shape - this renders as
 * "Button ReportButton PostReportButton", so the shared styling and the shared
 * click handler still find it while the post's own identity is there to style
 * and select on.
 */
class PostReportButton extends ReportButton
{
    public function __construct(int $post_id)
    {
        parent::__construct('post', $post_id);
    }
}
