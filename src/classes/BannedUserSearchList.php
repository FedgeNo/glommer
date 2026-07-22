<?php

declare(strict_types=1);

/**
 * The banned accounts matching a moderator's search: the same selection as
 * BannedUserList, narrowed to a username or display-name match. The Banned
 * Users page's search box repopulates the shared container with these and grows
 * them by infinite scroll through api/search-banned-users.php (see main.js);
 * the query and offset are seeded through the constructor.
 */
class BannedUserSearchList extends BannedUserList
{
    public string $query = '';

    protected function rows(): array
    {
        if ($this -> query === '') {
            return [];
        }

        $banned = 1;
        $limit = static::PAGE_SIZE + 1;

        // Escape LIKE wildcards so a literal % or _ in the query doesn't match
        // everything.
        $like = '%' . addcslashes($this -> query, '\\%_') . '%';

        return DB::rows('
SELECT *
    FROM `Users`
    WHERE `banned` = ? AND (`slug` LIKE ? OR `title` LIKE ?)
    ORDER BY `userId` DESC
    LIMIT ? OFFSET ?
', 'BannedUser', 'issii', $banned, $like, $like, $limit, $this -> offset);
    }
}
