<?php

declare(strict_types=1);

/** A member's like and its contribution to the post's stored total. */
class Like
{
    public static function exists(int $user_id, int $post_id): bool
    {
        return DB::row('
SELECT `postId`
    FROM `Likes`
    WHERE `postId` = ? AND `userId` = ?
', 'Post', 'ii', $post_id, $user_id) !== null;
    }

    /** False on a duplicate delivery or a post that has already disappeared. */
    public static function create(int $user_id, int $post_id): bool
    {
        return DB::transaction(static function () use ($user_id, $post_id): bool {
            if (Post::lockForUpdate($post_id) === null) {
                return false;
            }

            $insert = DB::run('
INSERT INTO `Likes` (`postId`, `userId`)
    VALUES (?, ?)
    ON DUPLICATE KEY UPDATE `postId` = VALUES(`postId`)
', 'ii', $post_id, $user_id);
            $added = mysqli_stmt_affected_rows($insert) === 1;

            if ($added) {
                Post::adjustCounts($post_id, likes: 1);
            }

            return $added;
        });
    }

    public static function remove(int $user_id, int $post_id): void
    {
        DB::transaction(static function () use ($user_id, $post_id): void {
            if (Post::lockForUpdate($post_id) === null) {
                return;
            }

            $delete = DB::run('
DELETE FROM `Likes`
    WHERE `postId` = ? AND `userId` = ?
', 'ii', $post_id, $user_id);

            if (mysqli_stmt_affected_rows($delete) === 1) {
                Post::adjustCounts($post_id, likes: -1);
            }
        });
    }
}
