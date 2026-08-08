<?php

declare(strict_types=1);

/** The single row of counts SiteCounters reads in one query. */
class SiteCountersData
{
    public ?int $members = null;
    public ?int $joinedThisWeek = null;
    public ?int $postedThisWeek = null;
    public ?int $posts = null;
    public ?int $postsThisWeek = null;
    public ?int $deliveriesQueued = null;
    public ?int $deliveriesFailing = null;
}
