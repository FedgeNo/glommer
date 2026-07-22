<?php

declare(strict_types=1);

/**
 * One profile's posts as an RSS feed: the newest top-level posts they authored.
 */
class UserRSSFeed extends RSSFeed {
    public User $user;

    public function __construct(array|object|null $properties = null) {
        parent::__construct($properties);

        $name = $this -> user -> title ?: $this -> user -> slug;

        $this -> title = 'Posts by ' . $name . ' on ' . Config::get('siteTitle');
        $this -> link = ServerURL::absolute('/users/' . $this -> user -> slug . '/');
        $this -> description = $this -> title;
    }

    protected function rows(): array {
        $user_id = (int) $this -> user -> userId;

        $posts = Post::fromRowsWithItems(DB::rows('
SELECT *
    FROM `Posts`
    WHERE `parentId` IS NULL AND `userId` = ?
    ORDER BY `postId` DESC
    LIMIT ?
', 'Post', 'ii', $user_id, static::LIMIT));

        return array_map(RSSItem::fromPost(...), $posts);
    }
}
