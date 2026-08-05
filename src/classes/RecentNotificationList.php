<?php

declare(strict_types=1);

/**
 * The handful of most-recent notifications shown in the nav dropdown - the
 * same self-fetching NotificationList, just fewer.
 *
 * It advertises no scroll configuration, and that is what keeps it out of the
 * infinite scroller: the scroller binds to every element carrying one, so
 * inheriting the page list's had it paging the dropdown - older notifications
 * loading into a panel five items tall that nobody had scrolled. "Show all" is
 * the way to the rest.
 */
class RecentNotificationList extends NotificationList
{
    public const PAGE_SIZE = 5;

    /**
     * @return array<string, string>
     */
    protected function dataAttributes(): array
    {
        return [];
    }
}
