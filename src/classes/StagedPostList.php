<?php

declare(strict_types=1);

/**
 * Everything the viewer has written but not published, newest first. Capped
 * at fifty by api/stage-post.php, so this never paginates.
 */
class StagedPostList extends ItemList
{
    public ?string $class = 'StagedPostList';

    protected string $emptyNotice = '';

    public function __construct(array|object|null $properties = null)
    {
        $this -> emptyNotice = (string) (Strings::for(self::class)['emptyNotice'] ?? '');
        parent::__construct($properties);
    }

    public ?int $userId = null;

    protected function rows(): array
    {
        return DB::rows('
SELECT *
    FROM `StagedPosts`
    WHERE `userId` = ?
    ORDER BY `stagedPostId` DESC
', 'StagedPostCard', 'i', (int) $this -> userId);
    }
}
