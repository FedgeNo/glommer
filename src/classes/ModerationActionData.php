<?php

declare(strict_types=1);

/** The columns a ModerationActions row hydrates into, plus who did it. */
class ModerationActionData
{
    public ?int $actionId = null;
    public ?int $moderatorId = null;
    public ?string $action = null;
    public ?int $targetUserId = null;
    public ?string $type = null;
    public ?int $targetId = null;
    public ?int $reportId = null;
    public ?string $createdAt = null;

    /** Joined, so a page of the log costs one query rather than one per row. */
    public ?string $moderatorUsername = null;
    public ?string $targetUsername = null;
}
