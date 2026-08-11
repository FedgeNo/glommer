<?php

declare(strict_types=1);

/**
 * One profile's posts as an RSS feed: the newest top-level posts they authored.
 */
class UserRSSFeed extends RSSFeed
{
    public User $user;

    // Named at render off the account this feed is for, rather than copied out
    // of it at construction: the profile is already here to be read from, and a
    // renamed account would otherwise still be announced under the old title.
    public function toDOM(): \DOMElement
    {
        $name = $this -> user -> title ?: $this -> user -> slug;

        $this -> title = 'Posts by ' . $name . ' on ' . Config::get('siteTitle');
        $this -> link = ServerURL::absolute('/users/' . $this -> user -> slug . '/');
        $this -> description = $this -> title;

        return parent::toDOM();
    }

    protected function rows(): array
    {
        $user_id = (int) $this -> user -> userId;

        return DB::rows('
SELECT `Posts`.`postId`, `Posts`.`title`, `Posts`.`description`, `Posts`.`contentWarning`, `Posts`.`createdAt`, `Users`.`slug` AS `authorSlug`
    FROM `Posts`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Posts`.`parentId` IS NULL AND `Posts`.`userId` = ?
    ORDER BY `Posts`.`postId` DESC
    LIMIT ?
', 'RSSItem', 'ii', $user_id, static::LIMIT);
    }
}
