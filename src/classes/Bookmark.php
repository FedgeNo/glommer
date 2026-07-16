<?php

declare(strict_types=1);

/**
 * A user privately saving a post for later - separate from Like (which is
 * public and notifies the author). No notification, no Block gate: bookmarking
 * never reaches the other user, so there's nothing to guard against.
 */
class Bookmark
{
    /**
     * Toggles the bookmark and returns the new state.
     */
    public static function toggle(int $user_id, int $post_id): bool
    {
        $mysqli = DB::connection();

        $check_stmt = mysqli_prepare($mysqli, '
SELECT 1
    FROM `Bookmarks`
    WHERE `postId` = ? AND `userId` = ?
');
        mysqli_stmt_bind_param($check_stmt, 'ii', $post_id, $user_id);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);

        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            $delete_stmt = mysqli_prepare($mysqli, '
DELETE
    FROM `Bookmarks`
    WHERE `postId` = ? AND `userId` = ?
');
            mysqli_stmt_bind_param($delete_stmt, 'ii', $post_id, $user_id);
            mysqli_stmt_execute($delete_stmt);

            return false;
        }

        $insert_stmt = mysqli_prepare($mysqli, '
INSERT INTO `Bookmarks` (`postId`, `userId`)
    VALUES (?, ?)
');
        mysqli_stmt_bind_param($insert_stmt, 'ii', $post_id, $user_id);

        try {
            mysqli_stmt_execute($insert_stmt);
        } catch (\mysqli_sql_exception $exception) {
            // Same check-then-insert race Like.php guards against: 1062 means
            // a concurrent request already bookmarked it - treat that as
            // success rather than a 500.
            if ($exception -> getCode() !== 1062) {
                throw $exception;
            }
        }

        return true;
    }

    /**
     * @param int[] $post_ids
     * @return array<int, true> postId => true for each post $user_id has bookmarked
     */
    public static function bookmarkedByUserForPosts(array $post_ids, int $user_id): array
    {
        if ($post_ids === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($post_ids), '?'));

        $stmt = mysqli_prepare(DB::connection(), '
SELECT `postId`
    FROM `Bookmarks`
    WHERE `userId` = ? AND `postId` IN (' . $placeholders . ')
');
        mysqli_stmt_bind_param($stmt, 'i' . str_repeat('i', count($post_ids)), $user_id, ...$post_ids);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $bookmarked = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $bookmarked[(int) $row['postId']] = true;
        }

        return $bookmarked;
    }

    /**
     * A page of the user's saved posts, newest-saved-first (ordered by when
     * the bookmark was made, not when the post was created - unlike every
     * other feed in the app, which cursors on postId alone). $before_created_at/
     * $before_post_id are both null for the first page; both non-null for a
     * "load more" request, forming a keyset cursor over (createdAt, postId) so
     * bookmarks made in the same second don't skip or repeat across pages.
     *
     * @return array{rows: array[], hasMore: bool, oldestCreatedAt: ?string, oldestPostId: ?int}
     */
    public static function rowsForUser(int $user_id, int $limit, ?string $before_created_at = null, ?int $before_post_id = null): array
    {
        $mysqli = DB::connection();
        $fetch_limit = $limit + 1;
        $not_banned = 0;

        if ($before_created_at !== null && $before_post_id !== null) {
            $stmt = mysqli_prepare($mysqli, '
SELECT `Posts`.*, `Bookmarks`.`createdAt` AS `bookmarkedAt`
    FROM `Bookmarks`
    JOIN `Posts` ON `Posts`.`postId` = `Bookmarks`.`postId`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Bookmarks`.`userId` = ? AND `Users`.`banned` = ? AND (`Bookmarks`.`createdAt` < ? OR (`Bookmarks`.`createdAt` = ? AND `Bookmarks`.`postId` < ?))
    ORDER BY `Bookmarks`.`createdAt` DESC, `Bookmarks`.`postId` DESC
    LIMIT ?
');
            mysqli_stmt_bind_param($stmt, 'iissii', $user_id, $not_banned, $before_created_at, $before_created_at, $before_post_id, $fetch_limit);
        } else {
            $stmt = mysqli_prepare($mysqli, '
SELECT `Posts`.*, `Bookmarks`.`createdAt` AS `bookmarkedAt`
    FROM `Bookmarks`
    JOIN `Posts` ON `Posts`.`postId` = `Bookmarks`.`postId`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Bookmarks`.`userId` = ? AND `Users`.`banned` = ?
    ORDER BY `Bookmarks`.`createdAt` DESC, `Bookmarks`.`postId` DESC
    LIMIT ?
');
            mysqli_stmt_bind_param($stmt, 'iii', $user_id, $not_banned, $fetch_limit);
        }

        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $rows = [];
        $bookmarked_ats = [];

        while ($row = mysqli_fetch_assoc($result)) {
            // Stripped back off before the row reaches Post::fromRow(), which
            // blind-assigns every row key as a property - an un-stripped
            // extra column would land on the Post object itself. Tracked in
            // a parallel array (not just the last row seen) since the
            // lookahead row - and its createdAt - may get popped off below.
            $bookmarked_ats[] = (string) $row['bookmarkedAt'];
            unset($row['bookmarkedAt']);

            $rows[] = $row;
        }

        $has_more = count($rows) > $limit;

        if ($has_more) {
            array_pop($rows);
            array_pop($bookmarked_ats);
        }

        $last_index = count($rows) - 1;
        $oldest_created_at = $last_index >= 0 ? $bookmarked_ats[$last_index] : null;
        $oldest_post_id = $last_index >= 0 ? (int) $rows[$last_index]['postId'] : null;

        return [
            'rows' => $rows,
            'hasMore' => $has_more,
            'oldestCreatedAt' => $oldest_created_at,
            'oldestPostId' => $oldest_post_id,
        ];
    }
}
