<?php

declare(strict_types=1);

/**
 * A post, as the rest of the network reads it.
 *
 * The body is rendered by DeltaRenderer - the same renderer the page uses -
 * rather than by anything written for federation, so the copy that leaves here
 * is the copy that is shown here. A second renderer would drift from the first
 * and the two would quietly stop matching.
 *
 * Every post on a Glommer server is public, so addressing is always the same:
 * to the public collection, cc the author's followers. There is no per-post
 * visibility to map, which is why none appears here.
 *
 * A titled post goes out as an Article rather than a Note. Notes have no room
 * for a title, and dropping it or stuffing it into the body would both lose
 * what the author actually wrote; Article carries it in `name`, and the
 * implementations that matter render it.
 */
class ActivityPubNote
{
    /** The canonical URI of a post - its permalink, which is also its id. */
    public static function uriFor(Post $post, User $author): string
    {
        return ServerURL::absolute('/users/' . $author -> slug . '/' . (int) $post -> postId);
    }

    /**
     * The object a remote server stores. Null for a post that came in from
     * elsewhere: it already has an id on its own server and re-publishing it
     * under ours would be claiming someone else's writing.
     *
     * @return array<string, mixed>|null
     */
    public static function document(Post $post, User $author): ?array
    {
        if ($post -> postId === null || $post -> remoteObjectURI !== null || !ActivityPubActor::isLocal($author)) {
            return null;
        }

        $uri = self::uriFor($post, $author);
        $title = is_string($post -> title) ? trim($post -> title) : '';

        $document = [
            'id' => $uri,
            'type' => $title === '' ? 'Note' : 'Article',
            'attributedTo' => ActivityPubActor::uriFor($author),
            'content' => DeltaRenderer::toHTML(Delta::decode($post -> descriptionDelta)),
            'url' => $uri,
            'published' => ActivityPubActor::timestamp((string) $post -> createdAt),
            'to' => [ActivityPubActor::PUBLIC_AUDIENCE],
            'cc' => [ActivityPubActor::followersFor($author)],
            'sensitive' => false,
        ];

        if ($title !== '') {
            $document['name'] = $title;
        }

        // An edit is a fact about the object, not a separate one - a reader
        // that already has it needs to know its copy is stale.
        if ($post -> editedAt !== null) {
            $document['updated'] = ActivityPubActor::timestamp((string) $post -> editedAt);
        }

        $in_reply_to = self::parentURI($post);

        if ($in_reply_to !== null) {
            $document['inReplyTo'] = $in_reply_to;
        }

        $attachments = self::attachments($post);

        if ($attachments !== []) {
            $document['attachment'] = $attachments;
        }

        $tags = self::hashtags((int) $post -> postId);

        if ($tags !== []) {
            $document['tag'] = $tags;
        }

        return $document;
    }

    /**
     * The Create that carries a new post outward. Its id is the post's own with
     * a fragment: the activity and the object are different things and need
     * different identifiers, and deriving one from the other keeps it stable
     * across a redelivery.
     *
     * @return array<string, mixed>|null
     */
    public static function createActivity(Post $post, User $author): ?array
    {
        $object = self::document($post, $author);

        if ($object === null) {
            return null;
        }

        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $object['id'] . '#create',
            'type' => 'Create',
            'actor' => ActivityPubActor::uriFor($author),
            'published' => $object['published'],
            'to' => $object['to'],
            'cc' => $object['cc'],
            'object' => $object,
        ];
    }

    /**
     * The Update that tells followers an existing post changed. Same object,
     * re-sent - there is no diff in ActivityPub, the whole object is restated.
     *
     * @return array<string, mixed>|null
     */
    public static function updateActivity(Post $post, User $author): ?array
    {
        $object = self::document($post, $author);

        if ($object === null) {
            return null;
        }

        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $object['id'] . '#update-' . ($object['updated'] ?? $object['published']),
            'type' => 'Update',
            'actor' => ActivityPubActor::uriFor($author),
            'to' => $object['to'],
            'cc' => $object['cc'],
            'object' => $object,
        ];
    }

    /**
     * The Delete that withdraws a post. The object is named rather than
     * restated - it is gone, so there is nothing left to send - and it becomes
     * a Tombstone on the receiving side.
     *
     * @return array<string, mixed>
     */
    public static function deleteActivity(string $object_uri, User $author): array
    {
        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $object_uri . '#delete',
            'type' => 'Delete',
            'actor' => ActivityPubActor::uriFor($author),
            'to' => [ActivityPubActor::PUBLIC_AUDIENCE],
            'object' => [
                'id' => $object_uri,
                'type' => 'Tombstone',
            ],
        ];
    }

    /**
     * What this post is a reply to, if anything. A reply to something that came
     * in from elsewhere points back at that object's own URI rather than at our
     * local copy of it, so the thread joins up on every server rather than
     * forking at our boundary.
     */
    private static function parentURI(Post $post): ?string
    {
        if ($post -> parentId === null) {
            return null;
        }

        $parent = DB::row('
SELECT `Posts`.`postId`, `Posts`.`remoteObjectURI`, `Users`.`slug`
    FROM `Posts`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Posts`.`postId` = ?
', 'PostParentData', 'i', (int) $post -> parentId);

        if ($parent === null) {
            return null;
        }

        if (is_string($parent -> remoteObjectURI) && $parent -> remoteObjectURI !== '') {
            return $parent -> remoteObjectURI;
        }

        return ServerURL::absolute('/users/' . $parent -> slug . '/' . (int) $parent -> postId);
    }

    /**
     * The post's media. Type is named as ActivityStreams names it rather than
     * by our class, since that is what the receiver switches on.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function attachments(Post $post): array
    {
        $types = [
            'ImageItem' => 'Image',
            'VideoItem' => 'Video',
            'AudioItem' => 'Audio',
        ];

        $attachments = [];

        foreach ($post -> items as $item) {
            if (!$item instanceof FeedItem || !isset($types[(string) $item -> type])) {
                continue;
            }

            $attachment = [
                'type' => $types[(string) $item -> type],
                'url' => $item -> srcURL(),
            ];

            // Alt text is what a screen reader on the other server will read
            // out, so it travels with the file rather than being left behind.
            if (is_string($item -> altText) && $item -> altText !== '') {
                $attachment['name'] = $item -> altText;
            }

            $attachments[] = $attachment;
        }

        return $attachments;
    }

    /**
     * The post's hashtags, as the network expects them: a name with the hash
     * still on it, and a link back to this server's page for that tag.
     *
     * @return array<int, array<string, string>>
     */
    private static function hashtags(int $post_id): array
    {
        $rows = DB::rows('
SELECT `Hashtags`.`slug`
    FROM `PostHashtags`
    JOIN `Hashtags` ON `Hashtags`.`hashtagId` = `PostHashtags`.`hashtagId`
    WHERE `PostHashtags`.`postId` = ?
    ORDER BY `Hashtags`.`slug`
', 'HashtagData', 'i', $post_id);

        return array_map(static fn (HashtagData $row): array => [
            'type' => 'Hashtag',
            'name' => '#' . $row -> slug,
            'href' => ServerURL::absolute('/tags/' . $row -> slug),
        ], $rows);
    }
}
