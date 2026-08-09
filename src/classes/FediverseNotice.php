<?php

declare(strict_types=1);

/**
 * The one place an inbound Fediverse action becomes a notification - the
 * counterpart to FediversePublisher, which is the one place a local change
 * becomes something the network is told about.
 *
 * Everything a member here can do to somebody's post already rings a bell.
 * Somebody on another server doing the identical thing has to ring the same
 * one, or federation is a feature that only works in the direction the person
 * is looking: a reply from Mastodon lands in the thread and nobody is told,
 * and the author finds out by scrolling past their own post.
 *
 * Here rather than inside Notification because the decisions are federation's,
 * not the notification system's - that a remote actor is a shadow row like any
 * other, that a block is still a block across the boundary, and that only a
 * local post has an author here to tell.
 */
class FediverseNotice
{
    /**
     * Tells the author of a post here that somebody out on the network did
     * something to it. $type is an ordinary notification type ('like',
     * 'repost', 'reply', 'mention') - the wording, the push and the throttle
     * are the same ones a local action of that kind gets, because to the person
     * being told it is the same event.
     */
    public static function aboutPost(int $post_id, User $actor, string $type): ?int
    {
        if ($actor -> userId === null || (int) $actor -> banned === 1) {
            return null;
        }

        // Only a post of ours has an author here to tell. One that came from
        // elsewhere is its own server's to notify about.
        $post = DB::row('
SELECT `Posts`.`userId`
    FROM `Posts`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Posts`.`postId` = ? AND `Users`.`remoteActorURI` IS NULL
', 'Post', 'i', $post_id);

        if ($post === null) {
            return null;
        }

        return self::tell((int) $post -> userId, $actor, $type, $post_id) ? (int) $post -> userId : null;
    }

    /**
     * Tells the members a post names that it names them.
     *
     * Unlike aboutPost there is no local author to look up: the post belongs to
     * the actor, and it is whoever was named who hears about it. That is also
     * why the id travelling here is the naming post's rather than a parent's -
     * a mention is read on the post that makes it.
     *
     * @param int[] $user_ids
     */
    public static function mentioned(int $post_id, User $actor, array $user_ids): void
    {
        if ($actor -> userId === null || (int) $actor -> banned === 1) {
            return;
        }

        foreach ($user_ids as $user_id) {
            self::tell((int) $user_id, $actor, 'mention', $post_id);
        }
    }

    /** Tells a member that somebody out on the network followed them. */
    public static function followed(int $user_id, User $actor): void
    {
        if ($actor -> userId === null || (int) $actor -> banned === 1) {
            return;
        }

        self::tell($user_id, $actor, 'follow');
    }

    /**
     * Blocked either way means nothing is sent. Notification::create has no
     * opinion on blocks - the local paths that need one check at the call site
     * (see Mention::notify) - and an inbound action is exactly where it
     * matters, since a block someone can reach around from another server is
     * not a block.
     */
    private static function tell(int $user_id, User $actor, string $type, ?int $post_id = null): bool
    {
        if (Block::exists((int) $actor -> userId, $user_id)) {
            return false;
        }

        Notification::create($user_id, (int) $actor -> userId, $type, $post_id);

        return true;
    }

    /**
     * The members a post names, from the sender's own Mention tags.
     *
     * Read from `tag` rather than from the written words: the tag is what the
     * sending server means by a mention, it carries the actor URI outright, and
     * a handle typed into the text is not necessarily addressed to anybody.
     *
     * @param array<string, mixed> $object
     * @return int[] local user ids
     */
    public static function mentionedLocalUserIds(array $object): array
    {
        $tags = $object['tag'] ?? null;

        if (!is_array($tags)) {
            return [];
        }

        $user_ids = [];

        foreach ($tags as $tag) {
            if (!is_array($tag) || ($tag['type'] ?? null) !== 'Mention') {
                continue;
            }

            $href = $tag['href'] ?? null;

            if (!is_string($href) || !ActivityPubActor::isLocalActorURI($href)) {
                continue;
            }

            $slug = self::slugFromActorURI($href);

            if ($slug === null) {
                continue;
            }

            $user = DB::row('
SELECT `userId`
    FROM `Users`
    WHERE `slug` = ? AND `remoteActorURI` IS NULL AND `banned` = 0
', 'User', 's', $slug);

            if ($user !== null) {
                $user_ids[(int) $user -> userId] = (int) $user -> userId;
            }
        }

        return array_values($user_ids);
    }

    /** The member a local actor URI names, from /users/{slug} or /users/{slug}/. */
    private static function slugFromActorURI(string $uri): ?string
    {
        $path = parse_url($uri, PHP_URL_PATH);

        if (!is_string($path) || preg_match('#\A/users/([^/]+)/?\z#', $path, $matches) !== 1) {
            return null;
        }

        return rawurldecode($matches[1]);
    }
}
