<?php

declare(strict_types=1);

/**
 * A post written but not published: a draft, or a scheduled post waiting for
 * its clock. Rows here become Posts rows only at publish - deliberately not a
 * future timestamp on Posts, which would put a time predicate (and its index)
 * into every feed query forever.
 *
 * Publishing runs the same steps api/create-post.php runs for a text post:
 * the insert, the location row, hashtag and mention indexing, the friends-feed
 * fan-out, and the federation announce. Text, link and location only - a post
 * with attached media still publishes immediately through the composer.
 */
class StagedPost
{
    /** How far ahead a post may be scheduled. A year is a typo, not a plan. */
    public const MAX_DAYS_AHEAD = 90;

    public ?int $stagedPostId = null;
    public ?int $userId = null;
    public ?string $title = null;
    public ?string $description = null;
    public ?string $descriptionDelta = null;
    public ?string $linkURL = null;
    public ?float $latitude = null;
    public ?float $longitude = null;
    public int $sensitive = 0;
    public ?string $contentWarning = null;
    public ?string $publishAt = null;
    public ?string $createdAt = null;

    public static function load(int $staged_post_id): ?self
    {
        return DB::row('
SELECT *
    FROM `StagedPosts`
    WHERE `stagedPostId` = ?
', self::class, 'i', $staged_post_id);
    }

    /** @return self[] newest first - the shape the drafts page lists. */
    public static function allFor(int $user_id): array
    {
        return DB::rows('
SELECT *
    FROM `StagedPosts`
    WHERE `userId` = ?
    ORDER BY `stagedPostId` DESC
', self::class, 'i', $user_id);
    }

    public static function stage(
        int $user_id,
        ?string $title,
        ?string $description,
        ?string $description_delta,
        ?string $link_url,
        ?float $latitude,
        ?float $longitude,
        int $sensitive,
        ?string $publish_at,
        ?string $content_warning = null
    ): int {
        DB::run('
INSERT INTO `StagedPosts` (`userId`, `title`, `description`, `descriptionDelta`, `linkURL`, `latitude`, `longitude`, `sensitive`, `publishAt`, `contentWarning`)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
', 'issssddiss', $user_id, $title, $description, $description_delta, $link_url, $latitude, $longitude, $sensitive, $publish_at, $content_warning);

        return (int) mysqli_insert_id(DB::connection());
    }

    /**
     * Rewrites a staged post's fields, the schedule included - clearing it
     * turns a scheduled post back into a draft. Scoped to the owner inside
     * the UPDATE itself.
     */
    public static function update(
        int $staged_post_id,
        int $user_id,
        ?string $title,
        ?string $description,
        ?string $description_delta,
        ?string $link_url,
        ?float $latitude,
        ?float $longitude,
        ?string $publish_at,
        int $sensitive = 0,
        ?string $content_warning = null
    ): void {
        DB::run('
UPDATE `StagedPosts`
    SET `title` = ?, `description` = ?, `descriptionDelta` = ?, `linkURL` = ?, `latitude` = ?, `longitude` = ?, `publishAt` = ?, `sensitive` = ?, `contentWarning` = ?
    WHERE `stagedPostId` = ? AND `userId` = ?
', 'ssssddsisii', $title, $description, $description_delta, $link_url, $latitude, $longitude, $publish_at, $sensitive, $content_warning, $staged_post_id, $user_id);
    }

    /** Deletes without publishing. Only ever the owner's to call. */
    public static function discard(int $staged_post_id, int $user_id): void
    {
        DB::run('
DELETE
    FROM `StagedPosts`
    WHERE `stagedPostId` = ? AND `userId` = ?
', 'ii', $staged_post_id, $user_id);
    }

    /**
     * Publishes every scheduled row whose clock has passed. Called from the
     * upload worker's poll loop - the daemon that already publishes posts
     * later than they were written.
     */
    public static function publishDue(): void
    {
        $due = DB::rows('
SELECT *
    FROM `StagedPosts`
    WHERE `publishAt` IS NOT NULL AND `publishAt` <= NOW()
    ORDER BY `publishAt`
    LIMIT 20
', self::class);

        foreach ($due as $staged) {
            $staged -> publish(true);
        }
    }

    /**
     * Claim the current draft and assemble its post and delivery queue in one
     * transaction. A failed publish leaves the draft available to try again.
     *
     * $notify_author only when the clock published it - someone was elsewhere
     * and should hear. A publish-now click needs no notification that the
     * button they just pressed worked.
     */
    public function publish(bool $notify_author = false): ?int
    {
        $result = DB::transaction(function () use ($notify_author): ?array {
            $current = DB::row('
SELECT * FROM `StagedPosts`
    WHERE `stagedPostId` = ?
        AND (? = 0 OR (`publishAt` IS NOT NULL AND `publishAt` <= NOW()))
    FOR UPDATE
', self::class, 'ii', $this -> stagedPostId, (int) $notify_author);

            return $current ?-> publishLocked();
        });

        if ($result === null) {
            return null;
        }

        [$post_id, $user_id, $mentions, $excessive_mentions] = $result;
        Mention::notify($mentions, $user_id, $post_id);

        Mention::reportExcessive($post_id, $excessive_mentions);

        if ($notify_author) {
            Notification::create($user_id, $user_id, 'scheduledPostLive', $post_id, true);
        }

        return $post_id;
    }

    /** Called only with this draft's row locked inside publish(). */
    private function publishLocked(): ?array
    {
        $author = User::load((int) $this -> userId);

        if ($author === null || $author -> banned !== 0) {
            self::discard((int) $this -> stagedPostId, (int) $this -> userId);

            return null;
        }

        $affected = DB::run('
DELETE
    FROM `StagedPosts`
    WHERE `stagedPostId` = ?
', 'i', $this -> stagedPostId);

        // Someone else (a second worker pass, a publish-now racing the clock)
        // already claimed it; whoever deleted the row is the one publishing.
        if (mysqli_stmt_affected_rows($affected) === 0) {
            return null;
        }

        $description_ops = $this -> descriptionDelta !== null
            ? Delta::sanitize(Delta::decode($this -> descriptionDelta))
            : [];

        DB::run('
INSERT INTO `Posts` (`userId`, `title`, `description`, `descriptionDelta`, `linkURL`, `sensitive`, `contentWarning`, `detectedLanguage`)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
', 'issssiss', $this -> userId, $this -> title, $this -> description, $this -> descriptionDelta, $this -> linkURL, $this -> sensitive, $this -> contentWarning, LanguageDetector::of((string) $this -> description));
        $post_id = (int) mysqli_insert_id(DB::connection());

        if ($this -> latitude !== null && $this -> longitude !== null) {
            PostLocation::save($post_id, $this -> latitude, $this -> longitude);
        }

        Hashtag::indexPost($post_id, $description_ops);
        $mentions = Mention::indexPost($post_id, $description_ops, false);
        Timeline::fanOutPost((int) $this -> userId, $post_id);

        $post = new Post($this);
        $post -> postId = $post_id;
        $post -> userId = (int) $this -> userId;
        $post -> createdAt = date('Y-m-d H:i:s');
        $post -> placeLabel = $this -> latitude === null ? null : (PostLocation::forPosts([$post_id])[$post_id]['placeLabel'] ?? null);
        $post -> remoteObjectURI = null;
        $post -> author = $author;

        FediversePublisher::published($post, $author);

        return [$post_id, (int) $this -> userId, $mentions, count(Delta::mentions($description_ops))];
    }
}
