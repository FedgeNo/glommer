<?php

declare(strict_types=1);

/**
 * The firehose: posts that arrived through a relay rather than through anybody
 * here following their author.
 *
 * Kept out of the main feed and the friends feed on purpose. Nobody asked for
 * any of this - it is every public post from every server subscribed to the
 * same relay - so it lives somewhere a person opens deliberately, rather than
 * turning up where they went to read the people they know.
 *
 * Members only, the same rule every other remote-origin listing follows: this
 * is other servers' writing, not content this site publishes to the world.
 */
class RelayFeedList extends FeedList
{
    protected string $feedType = 'relay';

    protected function rows(): array
    {
        $this -> emptyNotice = (string) (Strings::for(self::class)['emptyNotice'] ?? '');

        $not_banned = 0;
        $viewer_id = (int) Auth::id();

        // Driven from RelayPosts, which holds only the posts that came this
        // way - so the read is a walk of that table's primary key rather than
        // a filter over every post on the server.
        return Post::fromRowsWithItems(DB::rows('
SELECT `Posts`.*,
    (SELECT COUNT(*) FROM `Posts` `replies` WHERE `replies`.`parentId` = `Posts`.`postId`) AS `replyCount`,
    (SELECT COUNT(*) FROM `Likes` WHERE `Likes`.`postId` = `Posts`.`postId`) AS `likeCount`,
    EXISTS(SELECT 1 FROM `Likes` WHERE `Likes`.`postId` = `Posts`.`postId` AND `Likes`.`userId` = ?) AS `liked`,
    EXISTS(SELECT 1 FROM `Bookmarks` WHERE `Bookmarks`.`postId` = `Posts`.`postId` AND `Bookmarks`.`userId` = ?) AS `bookmarked`
    FROM `RelayPosts`
    JOIN `Posts` ON `Posts`.`postId` = `RelayPosts`.`postId`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Users`.`banned` = ?
    ORDER BY `RelayPosts`.`postId` DESC
    LIMIT ? OFFSET ?
', 'Post', 'iiiii', $viewer_id, $viewer_id, $not_banned, static::PAGE_SIZE + 1, $this -> offset));
    }
}
