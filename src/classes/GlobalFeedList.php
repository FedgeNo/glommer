<?php

declare(strict_types=1);

/**
 * The site-wide feed: the newest top-level posts by non-banned authors.
 *
 * Remote-origin posts are excluded: a followed Fediverse account's posts belong
 * in the feed of whoever followed it, not in the one served to everybody.
 *
 * STRAIGHT_JOIN pins the join order to Posts first: it walks the index
 * backward and stops once the page is full. Left to cost estimates, the
 * optimizer drives from Users instead, which collects and filesorts every
 * non-banned author's top-level posts to serve a 21-row page (measured ~270x
 * slower at 40k posts).
 *
 * FORCE INDEX is the same fight one step further. Left to itself the optimizer
 * reaches for the unique key on remoteObjectURI, which groups the local posts
 * together but carries no order within the group - so it filesorts every one of
 * them to serve a 21-row page. remoteObjectURI_postId has postId ordered inside
 * the NULL group, so the page is read straight off the index and the read stops
 * when it is full. It is pinned because the optimizer counts rows examined and
 * cannot see that LIMIT ends the ordered read early. Any change to this WHERE
 * clause needs the index changed to match, or the hint dropped.
 */
class GlobalFeedList extends FeedList
{
    protected string $feedType = 'global';

    protected function rows(): array
    {
        $not_banned = 0;
        $viewer_id = (int) Auth::id();

        return Post::fromRowsWithItems(DB::rows('
SELECT STRAIGHT_JOIN `Posts`.*,
    (SELECT COUNT(*) FROM `Posts` `replies` WHERE `replies`.`parentId` = `Posts`.`postId`) AS `replyCount`,
    (SELECT COUNT(*) FROM `Likes` WHERE `Likes`.`postId` = `Posts`.`postId`) AS `likeCount`,
    EXISTS(SELECT 1 FROM `Likes` WHERE `Likes`.`postId` = `Posts`.`postId` AND `Likes`.`userId` = ?) AS `liked`,
    EXISTS(SELECT 1 FROM `Bookmarks` WHERE `Bookmarks`.`postId` = `Posts`.`postId` AND `Bookmarks`.`userId` = ?) AS `bookmarked`
    FROM `Posts` FORCE INDEX (`remoteObjectURI_postId`)
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Users`.`banned` = ? AND `Posts`.`remoteObjectURI` IS NULL
    ORDER BY `Posts`.`postId` DESC
    LIMIT ? OFFSET ?
', 'Post', 'iiiii', $viewer_id, $viewer_id, $not_banned, static::PAGE_SIZE + 1, $this -> offset));
    }
}
