<?php

declare(strict_types=1);

/**
 * The handful of most-recent notifications shown in the nav dropdown - the same
 * self-fetching NotificationList, just fewer. It still carries a data-has-more,
 * but the dropdown is deliberately excluded from the infinite-scroll handler
 * (main.js), so "Show all" is the only way to the rest.
 */
class RecentNotificationList extends NotificationList
{
    public const PAGE_SIZE = 5;
}
