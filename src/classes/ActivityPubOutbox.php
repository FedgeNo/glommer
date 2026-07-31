<?php

declare(strict_types=1);

/**
 * One page of a member's posts, wrapped as the Create activities that carried
 * them - which is how a remote server backfills an account it has just started
 * following.
 *
 * Newest first, and offset-paged like every other list here.
 */
class ActivityPubOutbox
{
    public const PAGE_SIZE = 20;

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function activitiesFor(User $user, int $page): array
    {
        $offset = (max(1, $page) - 1) * self::PAGE_SIZE;
        $limit = self::PAGE_SIZE;

        // The member's own writing only: a row carrying a remoteObjectURI came
        // in from somewhere else and is not ours to publish back out.
        $posts = DB::rows('
SELECT *
    FROM `Posts`
    WHERE `userId` = ? AND `remoteObjectURI` IS NULL
    ORDER BY `postId` DESC
    LIMIT ? OFFSET ?
', 'Post', 'iii', (int) $user -> userId, $limit, $offset);

        if ($posts === []) {
            return [];
        }

        $activities = [];

        foreach (Post::fromRowsWithItems($posts) as $post) {
            $post -> author = $user;
            $activity = ActivityPubNote::createActivity($post, $user);

            if ($activity !== null) {
                // Inside a collection the context is carried by the collection
                // itself, so repeating it on every item is noise.
                unset($activity['@context']);
                $activities[] = $activity;
            }
        }

        return $activities;
    }
}
