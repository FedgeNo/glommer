<?php

declare(strict_types=1);

/**
 * The replies under a post. main.js locates it by its class name to insert
 * newly posted replies at the top, and grows it by infinite scroll off the
 * data-* attributes. Build with the post whose replies these are:
 * new ReplyList(['parentId' => 5]).
 */
class ReplyList extends ItemList
{
    public ?string $class = 'ReplyList d-flex flex-column';

    public ?int $parentId = null;

    protected function rows(): array
    {
        $not_banned = 0;

        return Post::withItemsAndCounts(DB::rows('
SELECT `Posts`.*
    FROM `Posts`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Posts`.`parentId` = ? AND `Users`.`banned` = ?
    ORDER BY `Posts`.`postId` DESC
    LIMIT ? OFFSET ?
', 'Post', 'iiii', (int) $this -> parentId, $not_banned, static::PAGE_SIZE + 1, $this -> offset));
    }

    public function toDOM(): \DOMElement
    {
        if ($this -> parentId !== null) {
            $this -> attributes['data-parent-id'] = (string) $this -> parentId;
        }

        return parent::toDOM();
    }
}
