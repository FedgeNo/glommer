<?php

declare(strict_types=1);

/**
 * The /nearby page: posts closest to a point, newest first.
 *
 * Deliberately k-nearest rather than a radius search. A radius needs a constant
 * that is wrong at every scale - 50km shows an empty page on a quiet site and a
 * firehose on a busy one - whereas "the NEAREST_LIMIT closest posts, whatever
 * the distance" casts a wide net while there's little to find and tightens to
 * genuinely local content as density grows, with nothing to tune.
 *
 * Distance decides membership only; the page is then ordered by postId so it
 * reads like an ordinary timeline. Ranking by distance would put an older post
 * above a newer one for being forty metres closer, which is not what anyone
 * wants from a feed.
 *
 * Build with new NearbyFeedList(['latitude' => 49.28, 'longitude' => -123.09]).
 */
class NearbyFeedList extends ItemList
{
    public ?string $class = 'NearbyFeedList';
    public array $mixins = ['FeedList', 'd-flex', 'flex-column'];

    /** How many of the closest posts are eligible, before the newest-first cut. */
    public const NEAREST_LIMIT = 1000;

    public ?float $latitude = null;
    public ?float $longitude = null;

    protected function rows(): array
    {
        $not_banned = 0;
        $viewer_id = (int) Auth::id();

        // The inner query ranks every located post by great-circle distance and
        // keeps the closest NEAREST_LIMIT; the outer one hydrates those and
        // orders them newest-first. LEAST(1, ...) clamps the cosine before
        // ACOS: floating point can push an exact-match point a hair above 1,
        // where ACOS returns NULL and the post silently vanishes from the feed.
        return Post::fromRowsWithItems(DB::rows('
SELECT `Posts`.*,
    (SELECT COUNT(*) FROM `Posts` `replies` WHERE `replies`.`parentId` = `Posts`.`postId`) AS `replyCount`,
    (SELECT COUNT(*) FROM `Likes` WHERE `Likes`.`postId` = `Posts`.`postId`) AS `likeCount`,
    EXISTS(SELECT 1 FROM `Likes` WHERE `Likes`.`postId` = `Posts`.`postId` AND `Likes`.`userId` = ?) AS `liked`,
    EXISTS(SELECT 1 FROM `Bookmarks` WHERE `Bookmarks`.`postId` = `Posts`.`postId` AND `Bookmarks`.`userId` = ?) AS `bookmarked`
    FROM (
        SELECT `postId`
            FROM `PostLocations`
            ORDER BY ACOS(LEAST(1,
                COS(RADIANS(?)) * COS(RADIANS(`latitude`)) * COS(RADIANS(`longitude`) - RADIANS(?))
                + SIN(RADIANS(?)) * SIN(RADIANS(`latitude`))
            ))
            LIMIT ' . self::NEAREST_LIMIT . '
    ) `nearest`
    JOIN `Posts` ON `Posts`.`postId` = `nearest`.`postId`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Users`.`banned` = ?
    ORDER BY `Posts`.`postId` DESC
    LIMIT ? OFFSET ?
', 'Post', 'iidddiii', $viewer_id, $viewer_id, $this -> latitude, $this -> longitude, $this -> latitude, $not_banned, static::PAGE_SIZE + 1, $this -> offset));
    }

    protected function dataAttributes(): array
    {
        // The origin rides along in the scroll config: InfiniteScroller sends
        // every key that isn't endpoint/itemType/direction with each request, so
        // page two ranks from the same point page one did.
        $this -> attributes['data-infinite-scroll'] = json_encode([
            'endpoint' => '/api/nearby-history',
            'itemType' => 'Post',
            'latitude' => $this -> latitude,
            'longitude' => $this -> longitude,
        ]);

        return [];
    }
}
