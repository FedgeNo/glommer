<?php

declare(strict_types=1);

/**
 * The one place a local change becomes something the network is told about.
 *
 * Called from the endpoints that change a post rather than from inside Post
 * itself, because publishing is a consequence of a person doing something, not
 * of a row changing - a moderator's deletion and an author's deletion are the
 * same row operation and want the same announcement, but a backfill rewriting
 * rows does not want any.
 *
 * Everything here queues and returns. Delivery is the worker's job; a person
 * pressing Post waits for their post, not for a thousand other servers.
 */
class FediversePublisher
{
    /** Announces a new post to the author's followers. */
    public static function published(Post $post, User $author): void
    {
        $activity = ActivityPubNote::createActivity($post, $author);

        if ($activity !== null) {
            FediverseDelivery::fanOut($author, $activity);
        }
    }

    /** Restates a post that changed. ActivityPub has no diff - the whole object goes again. */
    public static function updated(Post $post, User $author): void
    {
        $activity = ActivityPubNote::updateActivity($post, $author);

        if ($activity !== null) {
            FediverseDelivery::fanOut($author, $activity);
        }
    }

    /**
     * Withdraws a post. Built from the id rather than the row because by the
     * time anyone wants to announce a deletion the row is the one thing that no
     * longer exists - so the caller takes the URI first, while it can.
     */
    public static function deleted(string $object_uri, User $author): void
    {
        FediverseDelivery::fanOut($author, ActivityPubNote::deleteActivity($object_uri, $author));
    }

    /**
     * Tells followers a member's profile changed - display name, bio, avatar.
     * The whole actor document goes again, since ActivityPub restates rather
     * than diffs.
     */
    public static function profileUpdated(User $author): void
    {
        $actor = ActivityPubActor::document($author);

        if ($actor === null) {
            return;
        }

        // The context belongs to the activity here, not to the object inside
        // it, so it is not repeated.
        unset($actor['@context']);

        FediverseDelivery::fanOut($author, [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => ActivityPubActor::uriFor($author) . '#update-' . time(),
            'type' => 'Update',
            'actor' => ActivityPubActor::uriFor($author),
            'to' => [ActivityPubActor::PUBLIC_AUDIENCE],
            'object' => $actor,
        ]);
    }

    /**
     * Withdraws a whole account. Sent while the member still exists, because
     * the followers to send it to are about to be deleted with them - and a
     * Delete nobody is left to address reaches nobody.
     */
    public static function accountDeleted(User $author): void
    {
        if (!ActivityPubActor::isLocal($author) || $author -> userId === null) {
            return;
        }

        $uri = ActivityPubActor::uriFor($author);

        FediverseDelivery::fanOut($author, [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $uri . '#delete',
            'type' => 'Delete',
            'actor' => $uri,
            'to' => [ActivityPubActor::PUBLIC_AUDIENCE],
            'object' => $uri,
        ]);
    }

    /**
     * The URI a post will be withdrawn under, read before it is deleted. Null
     * when there is nothing to announce - a post that came in from elsewhere is
     * not ours to withdraw, and its own server will send its own Delete.
     */
    public static function objectURIFor(int $post_id): ?string
    {
        $row = DB::row('
SELECT `Posts`.`postId`, `Posts`.`remoteObjectURI`, `Users`.`slug`
    FROM `Posts`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Posts`.`postId` = ? AND `Posts`.`remoteObjectURI` IS NULL AND `Users`.`remoteActorURI` IS NULL
', 'PostParentData', 'i', $post_id);

        return $row === null ? null : ServerURL::absolute('/users/' . $row -> slug . '/' . (int) $row -> postId);
    }

    /**
     * The member who wrote a post, for the times the person acting is not its
     * author - a moderator deleting reported content. The withdrawal still goes
     * out as the author, because it is their object being withdrawn and their
     * followers holding a copy of it.
     */
    public static function authorOf(int $post_id): ?User
    {
        $post = DB::row('
SELECT `userId`
    FROM `Posts`
    WHERE `postId` = ?
', 'Post', 'i', $post_id);

        if ($post === null) {
            return null;
        }

        $author = User::load((int) $post -> userId);

        return $author !== null && ActivityPubActor::isLocal($author) ? $author : null;
    }
}
