<?php

declare(strict_types=1);

/**
 * The whole-site feed for RSS readers: the newest top-level posts by non-banned
 * local authors - the same selection the home page's global feed shows.
 *
 * STRAIGHT_JOIN pins the join order to Posts first so it walks parentId_postId
 * backward and stops once the page is full; left to cost estimates the
 * optimizer drives from Users and filesorts every non-banned author's top-level
 * posts. Remote-origin posts are excluded: a followed Fediverse account's posts
 * aren't ours to syndicate from our own domain.
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
    FROM `Posts`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Posts`.`parentId` IS NULL AND `Users`.`banned` = ? AND `Posts`.`remoteObjectURI` IS NULL
    ORDER BY `Posts`.`postId` DESC
    LIMIT ?
', 'RSSItem', 'ii', $not_banned, static::LIMIT);
    }
}
