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
 * FORCE INDEX is the same fight one step further. Given the choice the
 * optimizer index_merges parentId_postId with remoteObjectURI, and intersecting
 * two indexes destroys the primary-key ordering this ORDER BY relies on - so it
 * filesorts the whole matching set for a 21-row page (measured 120ms vs 0.5ms
 * at 55k posts). parentId_remoteObjectURI_postId covers both WHERE columns and
 * carries postId in order, so the page is read straight off the index. It's
 * pinned because the optimizer counts rows examined and can't see that LIMIT
 * makes the ordered read stop early. Any change to this WHERE clause needs the
 * index changed to match, or the hint dropped.
 *
 * Replies are kept out of this one feed, unlike the others. It is the only
 * feed a signed-out visitor sees, and a local reply to a post on another
 * server is itself local - so it would pass the filter above and arrive
 * carrying a link to a remote post, which nobody signed out is allowed to
 * open.
 */
class GlobalFeedList extends FeedList
{
    protected string $feedType = 'global';

    public ?int $beforePostId = null;

    protected function rows(): array
    {
        $not_banned = 0;
        $viewer_id = (int) Auth::id();
        $boundary = $this -> beforePostId === null ? '' : ' AND `Posts`.`postId` < ?';
        $types = $this -> beforePostId === null ? 'iiii' : 'iiiii';
        $parameters = [$viewer_id, $viewer_id, $not_banned];

        if ($this -> beforePostId !== null) {
            $parameters[] = $this -> beforePostId;
        }

        $parameters[] = static::PAGE_SIZE + 1;

        return Post::fromRowsWithItems(DB::rows('
SELECT STRAIGHT_JOIN `Posts`.*,
    (SELECT COUNT(*) FROM `Posts` `replies` WHERE `replies`.`parentId` = `Posts`.`postId`) AS `replyCount`,
    (SELECT COUNT(*) FROM `Likes` WHERE `Likes`.`postId` = `Posts`.`postId`) AS `likeCount`,
    EXISTS(SELECT 1 FROM `Likes` WHERE `Likes`.`postId` = `Posts`.`postId` AND `Likes`.`userId` = ?) AS `liked`,
    EXISTS(SELECT 1 FROM `Bookmarks` WHERE `Bookmarks`.`postId` = `Posts`.`postId` AND `Bookmarks`.`userId` = ?) AS `bookmarked`
    FROM `Posts` FORCE INDEX (`parentId_remoteObjectURI_postId`)
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Posts`.`parentId` IS NULL AND `Users`.`banned` = ? AND `Posts`.`remoteObjectURI` IS NULL' . $boundary . '
    ORDER BY `Posts`.`postId` DESC
    LIMIT ?
', 'Post', $types, ...$parameters));
    }

    protected function cursor(): ?array
    {
        $last = end($this -> items);

        return $last instanceof Post ? ['postId' => (int) $last -> postId] : null;
    }
}
