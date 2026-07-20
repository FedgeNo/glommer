<?php

declare(strict_types=1);

/**
 * The Banned Users page's results area: 20 banned accounts at a time (newest
 * accounts first), grown by infinite scroll in main.js off the data-*
 * attributes here - the next page is fetched by offset, how many accounts
 * are already shown. The search box (BannedUserSearch) repopulates this same
 * container with matches.
 */
class BannedUserList extends UserListSection
{
    protected string $heading = 'Banned Users';

    protected function rows(): array
    {
        $banned = 1;

        return DB::rows('
SELECT *
    FROM `Users`
    WHERE `banned` = ?
    ORDER BY `userId` DESC
    LIMIT ? OFFSET ?
', 'BannedUser', 'iii', $banned, static::PAGE_SIZE + 1, $this -> offset);
    }
}
