<?php

declare(strict_types=1);

/**
 * Everything the viewer has written but not published, newest first. Capped
 * at fifty by api/stage-post.php, so this never paginates.
 */
class StagedPostList extends ItemList
{
    public ?string $class = 'StagedPostList';
    public array $mixins = ['d-flex', 'flex-column', 'gap-2'];

    protected string $emptyNotice = 'No drafts or scheduled posts. The composer\'s Save Draft and Schedule controls put them here.';

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
