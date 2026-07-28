<?php

declare(strict_types=1);

/**
 * The replies under a post. main.js locates it by its class name to insert
 * newly posted replies at the top, and grows it by infinite scroll off the
 * data-* attributes. Build with the post whose replies these are:
 * new ReplyList(['parentId' => 5]).
 */
class ReplyList extends ItemList
{
    public ?string $class = 'ReplyList d-flex flex-column';

    public ?int $parentId = null;

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
    FROM `Posts`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Posts`.`parentId` = ? AND `Users`.`banned` = ?
    ORDER BY `Posts`.`postId` DESC
    LIMIT ? OFFSET ?
', 'Post', 'iiiiii', $viewer_id, $viewer_id, (int) $this -> parentId, $not_banned, static::PAGE_SIZE + 1, $this -> offset));
    }

    protected function dataAttributes(): array
    {
        $this->attributes['data-infinite-scroll'] = json_encode([
            'endpoint' => '/api/reply-history',
            'itemType' => 'Post',
            'parentId' => (int) $this->parentId,
        ]);

        return [];
    }
}

