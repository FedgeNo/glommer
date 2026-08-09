<?php

declare(strict_types=1);

/**
 * The posts carrying one #tag.
 *
 * A tag page is public, and remote-origin posts are not: this site does not
 * represent other servers' writing to the world, the same rule a Fediverse
 * profile and the relay feed already follow. So a logged-out visitor sees
 * only what was written here, and a member sees the tag as it actually is
 * across everything this server holds.
 */
class TagFeedList extends FeedList
{
    protected string $feedType = 'tag';

    public ?string $tag = null;

    protected function rows(): array
    {
        $not_banned = 0;
        $viewer_id = (int) Auth::id();
        $local_only = Auth::check() ? '' : ' AND `Posts`.`remoteObjectURI` IS NULL';

        return Post::fromRowsWithItems(DB::rows('
SELECT `Posts`.*,
    (SELECT COUNT(*) FROM `Posts` `replies` WHERE `replies`.`parentId` = `Posts`.`postId`) AS `replyCount`,
    (SELECT COUNT(*) FROM `Likes` WHERE `Likes`.`postId` = `Posts`.`postId`) AS `likeCount`,
    EXISTS(SELECT 1 FROM `Likes` WHERE `Likes`.`postId` = `Posts`.`postId` AND `Likes`.`userId` = ?) AS `liked`,
    EXISTS(SELECT 1 FROM `Bookmarks` WHERE `Bookmarks`.`postId` = `Posts`.`postId` AND `Bookmarks`.`userId` = ?) AS `bookmarked`
    FROM `PostHashtags`
    JOIN `Hashtags` ON `Hashtags`.`hashtagId` = `PostHashtags`.`hashtagId`
    JOIN `Posts` ON `Posts`.`postId` = `PostHashtags`.`postId`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Hashtags`.`slug` = ? AND `Users`.`banned` = ?' . $local_only . '
    ORDER BY `Posts`.`postId` DESC
    LIMIT ? OFFSET ?
', 'Post', 'iisiii', $viewer_id, $viewer_id, (string) $this -> tag, $not_banned, static::PAGE_SIZE + 1, $this -> offset));
    }

    /**
     * @return array<string, mixed>
     */
    protected function scrollConfig(): array
    {
        return parent::scrollConfig() + ['tag' => (string) $this -> tag];
    }
}
