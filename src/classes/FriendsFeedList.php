<?php

declare(strict_types=1);

/**
 * A viewer's own timeline: the posts fanned out to them, including those from
 * the Fediverse accounts they follow.
 */
class FriendsFeedList extends FeedList
{
    protected string $feedType = 'friends';

    public ?int $userId = null;
    public ?string $beforeSortAt = null;
    public ?int $beforePostId = null;

    protected function rows(): array
    {
        $not_banned = 0;
        $viewer_id = (int) Auth::id();
        $has_cursor = $this -> beforeSortAt !== null && $this -> beforePostId !== null;
        $boundary = $has_cursor
            ? ' AND (`Timelines`.`sortAt` < ? OR (`Timelines`.`sortAt` = ? AND `Timelines`.`postId` < ?))'
            : '';
        $types = $has_cursor ? 'iiiissii' : 'iiiii';
        $parameters = [$viewer_id, $viewer_id, (int) $this -> userId, $not_banned];

        if ($has_cursor) {
            $parameters[] = $this -> beforeSortAt;
            $parameters[] = $this -> beforeSortAt;
            $parameters[] = $this -> beforePostId;
        }

        $parameters[] = static::PAGE_SIZE + 1;

        // A row sorts by the moment it entered this feed - a repost by when it
        // was reposted, a post by when it was posted - which sortAt carries so
        // the order comes straight off its index. Ordering by anything computed
        // here would instead sort the person's entire timeline every page.
        //
        // STRAIGHT_JOIN because the optimizer, left to itself, leads with a
        // covering scan of every unbanned user and sorts the lot in a
        // temporary table; measured, that plan is hundreds of times slower
        // than reading this person's timeline rows in index order and
        // stopping at the page.
        return Post::fromRowsWithItems(DB::rows('
SELECT STRAIGHT_JOIN `Posts`.*,
    `Timelines`.`sortAt` AS `sortAt`,
    `reposter`.`slug` AS `repostedBySlug`,
    `reposter`.`title` AS `repostedByTitle`,
    (SELECT COUNT(*) FROM `Posts` `replies` WHERE `replies`.`parentId` = `Posts`.`postId`) AS `replyCount`,
    (SELECT COUNT(*) FROM `Likes` WHERE `Likes`.`postId` = `Posts`.`postId`) AS `likeCount`,
    EXISTS(SELECT 1 FROM `Likes` WHERE `Likes`.`postId` = `Posts`.`postId` AND `Likes`.`userId` = ?) AS `liked`,
    EXISTS(SELECT 1 FROM `Bookmarks` WHERE `Bookmarks`.`postId` = `Posts`.`postId` AND `Bookmarks`.`userId` = ?) AS `bookmarked`
    FROM `Timelines`
    JOIN `Posts` ON `Posts`.`postId` = `Timelines`.`postId`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    LEFT JOIN `Users` `reposter` ON `reposter`.`userId` = `Timelines`.`reposterId`
    WHERE `Timelines`.`userId` = ? AND `Users`.`banned` = ?' . $boundary . '
    ORDER BY `Timelines`.`sortAt` DESC, `Timelines`.`postId` DESC
    LIMIT ?
', 'Post', $types, ...$parameters));
    }

    protected function cursor(): ?array
    {
        $last = end($this -> items);

        return $last instanceof Post && is_string($last -> sortAt)
            ? ['sortAt' => $last -> sortAt, 'postId' => (int) $last -> postId]
            : null;
    }
}
