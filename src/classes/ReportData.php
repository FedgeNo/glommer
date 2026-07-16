<?php

declare(strict_types=1);

/**
 * The columns Report's queries read off a Reports row (some fetch only a
 * subset of these - the rest just stay null). reporterUsername is the one
 * field that isn't a Reports column - it's rowsForAdmin()'s Users join.
 */
class ReportData
{
    public ?int $reportId = null;
    public ?int $reporterId = null;
    public ?string $targetType = null;
    public ?int $targetId = null;
    public ?string $reason = null;
    public ?string $snapshot = null;
    public ?string $createdAt = null;
    public ?string $reporterUsername = null;
}
