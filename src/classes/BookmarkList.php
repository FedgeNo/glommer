<?php

declare(strict_types=1);

/**
 * The /bookmarks page's list of the viewer's bookmarked posts, grown by
 * infinite scroll (main.js). Ordered by bookmarkId - insertion order, i.e. most
 * recently bookmarked first, which is the ordering this list is about ("most
 * recently bookmarked" and "most recently posted" are genuinely different
 * here). Build with new BookmarkList(['userId' => 5]).
 */
class BookmarkList extends ItemList
{
    public ?string $class = 'BookmarkList d-flex flex-column';

    public ?int $userId = null;

    protected function rows(): array
    {
        $not_banned = 0;
        $viewer_id = (int) Auth::id();

        return Post::fromRowsWithItems(DB::rows('
SELECT `Posts`.*,
    (SELECT COUNT(*) FROM `Posts` `replies` WHERE `replies`.`parentId` = `Posts`.`postId`) AS `replyCount`,
    (SELECT COUNT(*) FROM `Likes` WHERE `Likes`.`postId` = `Posts`.`postId`) AS `likeCount`,
    EXISTS(SELECT 1 FROM `Likes` WHERE `Likes`.`postId` = `Posts`.`postId` AND `Likes`.`userId` = ?) AS `liked`,
    EXISTS(SELECT 1 FROM `Bookmarks` WHERE `Bookmarks`.`postId` = `Posts`.`postId` AND `Bookmarks`.`userId` = ?) AS `bookmarked`
    FROM `Bookmarks`
    JOIN `Posts` ON `Posts`.`postId` = `Bookmarks`.`postId`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Bookmarks`.`userId` = ? AND `Users`.`banned` = ?
    ORDER BY `Bookmarks`.`bookmarkId` DESC
    LIMIT ? OFFSET ?
', 'Post', 'iiiiii', $viewer_id, $viewer_id, (int) $this -> userId, $not_banned, static::PAGE_SIZE + 1, $this -> offset));
    }
}
