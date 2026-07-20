<?php

declare(strict_types=1);

/**
 * Friends-of-friends, ranked by how many of the viewer's friends each one is
 * already friends with, then filtered to the accounts the viewer may actually
 * be shown. Falls back to random accounts when the viewer has no friends yet,
 * or when ranking turns up nobody eligible, so the list is never empty.
 *
 * Blocked accounts are left out, in either direction, for the same reason
 * RandomUserList leaves them out: a suggestion is the site offering someone
 * up unprompted.
 */
class EligibleSuggestedUserList extends RandomUserList
{
    /**
     * Ranked candidates are capped well above a page so the eligibility pass
     * still has plenty to fall through when some are banned or blocked.
     * Without it, both this result set and the bound-parameter count below
     * scale with a well-connected viewer's entire two-hop neighbourhood.
     */
    private const MAX_RANKED_CANDIDATES = 200;


    protected function rows(): array
    {
        $friend_ids = User::load((int) Auth::id()) ?-> friendIds() ?? [];
        $mutual_counts = $this -> mutualFriendCounts($friend_ids);

        if ($mutual_counts === []) {
            return parent::rows();
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

        if ($ranked === []) {
            return parent::rows();
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
