<?php

declare(strict_types=1);

/**
 * The whole-site feed for RSS readers: the newest top-level posts by non-banned
 * local authors - the same selection the home page's global feed shows.
 *
 * STRAIGHT_JOIN pins the join order to Posts first, and FORCE INDEX pins the
 * index: left to cost estimates the optimizer reads Posts backward off its
 * PRIMARY key instead, filtering parentId/remoteObjectURI as it goes rather
 * than jumping straight to the matching rows - the same problem GlobalFeedList
 * hits at scale (see that class for the measured difference). Remote-origin
 * posts are excluded: a followed Fediverse account's posts aren't ours to
 * syndicate from our own domain.
 */
class SiteRSSFeed extends RSSFeed {
    public function __construct(array|object|null $properties = null) {
        parent::__construct($properties);

        $this -> title = (string) Config::get('siteTitle');
        $this -> link = ServerURL::absolute('/');
        $this -> description = SiteInfo::description();
    }

    protected function rows(): array {
        $not_banned = 0;

        return DB::rows('
SELECT STRAIGHT_JOIN `Posts`.`postId`, `Posts`.`title`, `Posts`.`description`, `Posts`.`createdAt`, `Users`.`slug` AS `authorSlug`
    FROM `Posts` FORCE INDEX (`parentId_remoteObjectURI_postId`)
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Posts`.`parentId` IS NULL AND `Users`.`banned` = ? AND `Posts`.`remoteObjectURI` IS NULL
    ORDER BY `Posts`.`postId` DESC
    LIMIT ?
', 'RSSItem', 'ii', $not_banned, static::LIMIT);
    }
}
