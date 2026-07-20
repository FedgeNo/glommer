<?php

declare(strict_types=1);

/**
 * The site-wide feed: the newest top-level posts by non-banned authors.
 *
 * Remote-origin posts are excluded: a followed Fediverse account's posts belong
 * in the feed of whoever followed it, not in the one served to everybody.
 *
 * STRAIGHT_JOIN pins the join order to Posts first: it walks parentId_postId
 * backward and stops once the page is full. Left to cost estimates, the
 * optimizer drives from Users instead, which collects and filesorts every
 * non-banned author's top-level posts to serve a 21-row page (measured ~270x
 * slower at 40k posts).
 */
class GlobalFeedList extends FeedList
{
    protected string $feedType = 'global';

    protected function rows(): array
    {
        $not_banned = 0;

        return Post::withItemsAndCounts(DB::rows('
SELECT STRAIGHT_JOIN `Posts`.*
    FROM `Posts`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Posts`.`parentId` IS NULL AND `Users`.`banned` = ? AND `Posts`.`remoteObjectURI` IS NULL
    ORDER BY `Posts`.`postId` DESC
    LIMIT ? OFFSET ?
', 'Post', 'iii', $not_banned, static::PAGE_SIZE + 1, $this -> offset));
    }
}
