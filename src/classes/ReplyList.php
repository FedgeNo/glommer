<?php

declare(strict_types=1);

/**
 * The replies under a post. Controllers.js locates it by its class name to insert
 * newly posted replies at the top, and grows it by infinite scroll off the
 * data-* attributes. Build with the post whose replies these are:
 * new ReplyList(['parentId' => 5]).
 */
class ReplyList extends ItemList
{
    public ?string $class = 'ReplyList';

    public ?int $parentId = null;

    protected function rows(): array
    {
        $not_banned = 0;
        $viewer_id = (int) Auth::id();
        $visibility = Auth::check() ? '' : '
        AND `Posts`.`remoteObjectURI` IS NULL AND `Users`.`remoteActorURI` IS NULL
        AND EXISTS (
            SELECT 1 FROM `Posts` `parent`
                JOIN `Users` `parentAuthor` ON `parentAuthor`.`userId` = `parent`.`userId`
                WHERE `parent`.`postId` = `Posts`.`parentId`
                    AND `parent`.`remoteObjectURI` IS NULL AND `parentAuthor`.`remoteActorURI` IS NULL
                    AND `parentAuthor`.`banned` = 0
        )';

        return Post::fromRowsWithItems(DB::rows('
SELECT `Posts`.*,
    EXISTS(SELECT 1 FROM `Likes` WHERE `Likes`.`postId` = `Posts`.`postId` AND `Likes`.`userId` = ?) AS `liked`,
    EXISTS(SELECT 1 FROM `Bookmarks` WHERE `Bookmarks`.`postId` = `Posts`.`postId` AND `Bookmarks`.`userId` = ?) AS `bookmarked`
    FROM `Posts`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Posts`.`parentId` = ? AND `Users`.`banned` = ? ' . $visibility . '
    ORDER BY `Posts`.`postId` DESC
    LIMIT ? OFFSET ?
', 'Post', 'iiiiii', $viewer_id, $viewer_id, (int) $this -> parentId, $not_banned, static::PAGE_SIZE + 1, $this -> offset));
    }

    /**
     * @return array<string, string>
     */
    protected function dataAttributes(): array
    {
        return ['data-infinite-scroll' => (string) json_encode([
            'endpoint' => '/api/reply-history',
            'itemType' => 'Post',
            'parentId' => (int) $this -> parentId,
        ])];
    }
}
