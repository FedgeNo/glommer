<?php

declare(strict_types=1);

/**
 * The posts carrying one #tag.
 *
 * Remote-origin posts are excluded: a followed Fediverse account's posts only
 * belong in the specific follower's own feed, never in one anyone can browse
 * to, tag pages included.
 */
class TagFeedList extends FeedList
{
    protected string $feedType = 'tag';

    public ?string $tag = null;

    protected function rows(): array
    {
        $not_banned = 0;

        return Post::withItemsAndCounts(DB::rows('
SELECT `Posts`.*
    FROM `PostHashtags`
    JOIN `Hashtags` ON `Hashtags`.`hashtagId` = `PostHashtags`.`hashtagId`
    JOIN `Posts` ON `Posts`.`postId` = `PostHashtags`.`postId`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Hashtags`.`slug` = ? AND `Posts`.`parentId` IS NULL AND `Users`.`banned` = ? AND `Posts`.`remoteObjectURI` IS NULL
    ORDER BY `Posts`.`postId` DESC
    LIMIT ? OFFSET ?
', 'Post', 'siii', (string) $this -> tag, $not_banned, static::PAGE_SIZE + 1, $this -> offset));
    }

    /**
     * @return array<string, string>
     */
    protected function dataAttributes(): array
    {
        return parent::dataAttributes() + ['data-tag' => (string) $this -> tag];
    }
}
