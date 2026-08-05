<?php

declare(strict_types=1);

/**
 * The results area of a UserSearch: the ranked suggestions until something is
 * typed, then the accounts matching the current query. The client rebuilds it
 * from api/search-users.php as you type (see main.js).
 *
 * The suggestions are friends-of-friends, ranked by how many of the viewer's
 * friends each one is already friends with, then filtered to the accounts they
 * may actually be shown. Empty when there is nobody to suggest, which is the
 * honest answer on a server where everyone already knows each other.
 *
 * Blocked accounts are left out of the suggestions in either direction - a
 * suggestion is the site offering someone up unprompted - but deliberately NOT
 * filtered out of a query's matches: a search is the viewer looking for a
 * specific account, and they may be looking exactly in order to unblock.
 */
class UserSearchList extends UserList
{
    /**
     * Ranked suggestion candidates are capped well above a page so the
     * eligibility pass still has plenty to fall through when some are banned
     * or blocked. Without it, both that result set and its bound-parameter
     * count scale with a well-connected viewer's entire two-hop neighbourhood.
     */
    private const MAX_RANKED_CANDIDATES = 200;

    public string $query = '';

    protected function rows(): array
    {
        // The two empty states are different questions and want different
        // answers: nobody to suggest is the ordinary state of a small server,
        // while nothing matching what was typed is about the typing. Set here
        // because rows() is what knows which of the two ran.
        $this -> emptyNotice = $this -> query === ''
            ? 'No suggestions right now - suggestions come from the friends of people you are already friends with. Search above to find someone by name.'
            : 'Nobody here matches that.';

        if ($this -> query === '') {
            return $this -> suggestionRows();
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

        // localMember leads the ordering so this site's own people always come
        // first. A server subscribed to a relay holds a shadow account for
        // every author whose post has passed through it - thousands of
        // strangers nobody here follows, against a membership that might be
        // eight - and without this the members someone was actually looking for
        // are somewhere on page forty.
        //
        // Remote accounts are still listed, and must be: finding one by handle
        // is how you follow it, and a blocked one has to stay findable to be
        // unblocked.
        //
        // Then nameMatch (a hit on the username or display name) orders every
        // name match ahead of a bio-only (full-text) match; userId breaks the
        // ties so the order is total and stable across the growing-offset
        // requests infinite scroll makes - without a tiebreaker, rows sharing
        // these values have no defined order and a page could repeat or skip
        // accounts.
        return DB::rows('
SELECT *, (`slug` LIKE ? OR `title` LIKE ?) AS `nameMatch`, (`remoteActorURI` IS NULL) AS `localMember`
    FROM `Users`
    WHERE (`slug` LIKE ? OR `title` LIKE ? OR MATCH(`description`) AGAINST(? IN BOOLEAN MODE))
        AND `userId` != ? AND `banned` = ?
    ORDER BY `localMember` DESC, `nameMatch` DESC, `userId` DESC
    LIMIT ? OFFSET ?
', 'OtherUser', 'sssssiiii', $like, $like, $like, $like, $ft_query, $viewer_id, $not_banned, $limit, $this -> offset);
    }

    /**
     * The suggestions themselves - what the box shows before anything is typed.
     */
    private function suggestionRows(): array
    {
        $friend_ids = User::load((int) Auth::id()) ?-> friendIds() ?? [];
        $mutual_counts = $this -> mutualFriendCounts($friend_ids);

        if ($mutual_counts === []) {
            return [];
        }

        $eligible = $this -> eligible(array_keys($mutual_counts));

        // Walked in ranked order, so the page keeps the mutual-friend
        // ordering rather than whatever order the eligibility query returned.
        $ranked = [];

        foreach (array_keys($mutual_counts) as $candidate_id) {
            if (isset($eligible[$candidate_id])) {
                $ranked[] = $eligible[$candidate_id];
            }
        }

        return array_slice($ranked, $this -> offset, static::PAGE_SIZE + 1);
    }

    /**
     * One query in each direction rather than a load()+friendIds() round trip
     * per friend, so cost doesn't scale with how many friends the viewer has.
     *
     * @param int[] $friend_ids
     * @return array<int, int> candidate userId => mutual friend count, highest first
     */
    private function mutualFriendCounts(array $friend_ids): array
    {
        if ($friend_ids === []) {
            return [];
        }

        $accepted_status = 'accepted';
        $not_banned = 0;
        $excluded_ids = array_merge($friend_ids, [(int) Auth::id()]);

        $friend_placeholders = implode(', ', array_fill(0, count($friend_ids), '?'));
        $excluded_placeholders = implode(', ', array_fill(0, count($excluded_ids), '?'));

        $params = array_merge(
            [$accepted_status, $not_banned],
            $friend_ids,
            $excluded_ids,
            [$accepted_status, $not_banned],
            $friend_ids,
            $excluded_ids,
            [self::MAX_RANKED_CANDIDATES]
        );
        $types = 'si' . str_repeat('i', count($friend_ids)) . str_repeat('i', count($excluded_ids))
            . 'si' . str_repeat('i', count($friend_ids)) . str_repeat('i', count($excluded_ids)) . 'i';

        $stmt = DB::run('
SELECT `candidateId`, COUNT(*) AS `mutualCount`
    FROM (
        SELECT `f`.`addresseeId` AS `candidateId`
            FROM `Friendships` `f`
            JOIN `Users` `u` ON `u`.`userId` = `f`.`addresseeId`
            WHERE `f`.`status` = ? AND `u`.`banned` = ?
                AND `f`.`requesterId` IN (' . $friend_placeholders . ') AND `f`.`addresseeId` NOT IN (' . $excluded_placeholders . ')
        UNION ALL
        SELECT `f`.`requesterId` AS `candidateId`
            FROM `Friendships` `f`
            JOIN `Users` `u` ON `u`.`userId` = `f`.`requesterId`
            WHERE `f`.`status` = ? AND `u`.`banned` = ?
                AND `f`.`addresseeId` IN (' . $friend_placeholders . ') AND `f`.`requesterId` NOT IN (' . $excluded_placeholders . ')
    ) `candidates`
    GROUP BY `candidateId`
    ORDER BY `mutualCount` DESC
    LIMIT ?
', $types, ...$params);
        $result = mysqli_stmt_get_result($stmt);

        $mutual_counts = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $mutual_counts[(int) $row['candidateId']] = (int) $row['mutualCount'];
        }

        return $mutual_counts;
    }

    /**
     * @param int[] $user_ids
     * @return array<int, OtherUser> userId => OtherUser, for those who aren't
     *                               banned and aren't blocked either way
     */
    private function eligible(array $user_ids): array
    {
        if ($user_ids === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($user_ids), '?'));
        $not_banned = 0;

        $viewer_id = (int) Auth::id();

        $params = array_merge($user_ids, [$not_banned, $viewer_id, $viewer_id]);

        $rows = DB::rows('
SELECT `u`.*
    FROM `Users` `u`
    WHERE `u`.`userId` IN (' . $placeholders . ') AND `u`.`banned` = ?
        AND NOT EXISTS (
            SELECT 1
                FROM `Blocks` `b`
                WHERE (`b`.`blockerId` = ? AND `b`.`blockedId` = `u`.`userId`) OR (`b`.`blockerId` = `u`.`userId` AND `b`.`blockedId` = ?)
        )
', 'OtherUser', str_repeat('i', count($user_ids)) . 'iii', ...$params);

        $users = [];

        foreach ($rows as $user) {
            $users[(int) $user -> userId] = $user;
        }

        return $users;
    }
}
