<?php

declare(strict_types=1);

/**
 * The results area of a UserSearch: the ranked suggestions until something is
 * typed, then the accounts matching the current query. The client rebuilds it
 * from api/search-users.php as you type (see main.js).
 *
 * Blocked accounts are deliberately NOT filtered out of a query's matches -
 * unlike the suggestion fallback this inherits, a search is the viewer
 * deliberately looking for a specific account, not the site putting one in
 * front of them unprompted.
 */
class UserSearchList extends EligibleSuggestedUserList
{
    public string $query = '';

    protected function rows(): array
    {
        if ($this -> query === '') {
            return parent::rows();
        }

        // Escape LIKE wildcards so a literal % or _ in the query doesn't match
        // everything.
        $like = '%' . addcslashes($this -> query, '\\%_') . '%';

        // The bio is searched full-text (whole-word / prefix); the username and
        // display name by substring. Each query word must prefix-match a bio
        // word (+word*); a query that's only punctuation leaves this empty, so
        // just the name LIKEs run.
        $ft_tokens = array_filter(preg_split('/[^A-Za-z0-9_]+/', $this -> query));
        $ft_query = implode(' ', array_map(static fn (string $token): string => '+' . $token . '*', $ft_tokens));

        $not_banned = 0;
        $viewer_id = (int) Auth::id();
        $limit = static::PAGE_SIZE + 1;

        // nameMatch (a hit on the username or display name) orders every name
        // match ahead of a bio-only (full-text) match; userId breaks the ties
        // so the order is total and stable across the growing-offset requests
        // infinite scroll makes - without a tiebreaker, rows sharing a
        // nameMatch value have no defined order and a page could repeat or skip
        // accounts.
        return DB::rows('
SELECT *, (`slug` LIKE ? OR `title` LIKE ?) AS `nameMatch`
    FROM `Users`
    WHERE (`slug` LIKE ? OR `title` LIKE ? OR MATCH(`description`) AGAINST(? IN BOOLEAN MODE))
        AND `userId` != ? AND `banned` = ?
    ORDER BY `nameMatch` DESC, `userId` DESC
    LIMIT ? OFFSET ?
', 'OtherUser', 'sssssiiii', $like, $like, $like, $like, $ft_query, $viewer_id, $not_banned, $limit, $this -> offset);
    }
}
