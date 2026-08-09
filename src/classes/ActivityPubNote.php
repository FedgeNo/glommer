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
     * The local post an object URI names, or null when it names something that
     * isn't one - the inverse of uriFor().
     *
     * Matched through the author as well as the id, so a URI naming the right
     * post under the wrong person resolves to nothing rather than to the post,
     * and never to something that arrived from elsewhere.
     */
    public static function localPostIdFor(string $object_uri): ?int
    {
        if (!ActivityPubActor::isLocalActorURI($object_uri)) {
            return null;
        }

        $path = parse_url($object_uri, PHP_URL_PATH);

        if (!is_string($path) || !preg_match('#\A/users/([^/]+)/([0-9]+)\z#', $path, $matches)) {
            return null;
        }

        $post = DB::row('
SELECT `Posts`.`postId`
    FROM `Posts`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Posts`.`postId` = ? AND `Users`.`slug` = ? AND `Posts`.`remoteObjectURI` IS NULL
', 'PostParentData', 'is', (int) $matches[2], rawurldecode($matches[1]));

        return $post === null ? null : (int) $post -> postId;
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
        if ($post -> postId === null || $post -> remoteObjectURI !== null || $author -> remoteActorURI !== null) {
            return null;
        }

        $uri = self::uriFor($post, $author);
        $title = is_string($post -> title) ? trim($post -> title) : '';
        $media = self::soleMediaItem($post);
        $poll = Poll::forPost((int) $post -> postId);

        // A post that is one video or one audio file IS that thing, rather than
        // a note with something attached - which is how PeerTube and Funkwhale
        // publish, and what a player on the other side goes looking for. A
        // titled one keeps its title either way.
        //
        // A poll likewise IS the post: ActivityPub has no separate poll object,
        // so a post carrying one becomes a Question. It comes first because a
        // Question that arrived as anything else is just a post whose choices
        // vanished, and a poll takes no media to compete with anyway.
        $type = match (true) {
            $poll !== null => 'Question',
            $media !== null => $media['type'],
            $title !== '' => 'Article',
            default => 'Note',
        };

        $document = [
            'id' => $uri,
            'type' => $type,
            'attributedTo' => ActivityPubActor::uriFor($author),
            'content' => DeltaRenderer::toHTML(Delta::decode($post -> descriptionDelta)),
            'url' => $uri,
            'published' => ActivityPubActor::timestamp((string) $post -> createdAt),
            'to' => [ActivityPubActor::PUBLIC_AUDIENCE],
            'cc' => [ActivityPubActor::followersFor($author)],
            'sensitive' => $post -> sensitive === 1,
        ];

        if ($title !== '') {
            $document['name'] = $title;
        }

        // The warning as the rest of the network reads one. Not the title:
        // `name` is what a post is called, `summary` is what to know before
        // reading it, and a receiver puts only the second behind a cover.
        if ($post -> contentWarning !== null && trim((string) $post -> contentWarning) !== '') {
            $document['summary'] = $post -> contentWarning;
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

        // A quote post names what it quotes three ways at once - quoteUrl
        // (the Misskey/Fedibird lineage most servers read), _misskey_quote
        // (its older spelling), and an FEP-e232 object link tag - because
        // there is no single property the whole network agrees on yet.
        $quoted_uri = self::quotedURI($post);

        if ($quoted_uri !== null) {
            $document['quoteUrl'] = $quoted_uri;
            $document['_misskey_quote'] = $quoted_uri;
            $document['tag'][] = [
                'type' => 'Link',
                'mediaType' => 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"',
                'href' => $quoted_uri,
                'name' => 'RE: ' . $quoted_uri,
            ];
        }

        if ($poll !== null) {
            $document += self::pollFields($poll);
        }

        if ($media !== null) {
            // The media is the object, so url carries both ways in: the page a
            // person opens, and the file a player streams. Attaching it as well
            // would hand the receiver the same thing twice.
            $document['url'] = [
                ['type' => 'Link', 'mediaType' => 'text/html', 'href' => $uri],
                ['type' => 'Link', 'mediaType' => $media['mediaType'], 'href' => $media['href']],
            ];
        } else {
            $attachments = self::attachments($post);

            if ($attachments !== []) {
                $document['attachment'] = $attachments;
            }
        }

        $tags = self::hashtags((int) $post -> postId);

        // Anyone named directly is addressed as well as tagged. The tag is what
        // renders the mention as a link on the far side; the addressing is what
        // actually gets the post to them.
        foreach (self::remoteRecipients($post) as $recipient) {
            $document['to'][] = $recipient['uri'];

            $tags[] = [
                'type' => 'Mention',
                'name' => $recipient['handle'],
                'href' => $recipient['uri'],
            ];
        }

        if ($tags !== []) {
            // Merged, never assigned - a quote post has already put its
            // FEP-e232 object link in here.
            $document['tag'] = array_merge($document['tag'] ?? [], $tags);
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
     * The remote accounts a post is aimed at directly, as well as at followers:
     * whoever wrote the thing it replies to, and anyone it mentions.
     *
     * Without these a reply never reaches the person replied to unless they
     * already follow the author, and a mention reaches nobody at all - the post
     * would go only to followers, which is not who it was addressed to.
     *
     * Keyed by actor URI so the same person named twice is one recipient.
     *
     * @return array<string, array{uri: string, handle: string, inbox: string}>
     */
    public static function remoteRecipients(Post $post): array
    {
        if ($post -> postId === null) {
            return [];
        }

        $recipients = [];

        // Whoever wrote the parent, when the parent came from elsewhere.
        if ($post -> parentId !== null) {
            $parent_author = DB::row('
SELECT `Users`.`slug`, `Users`.`remoteActorURI`, `Users`.`remoteActorInboxURL`
    FROM `Posts`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Posts`.`postId` = ? AND `Users`.`remoteActorURI` IS NOT NULL
', 'RemoteRecipientData', 'i', (int) $post -> parentId);

            self::addRecipient($recipients, $parent_author);
        }

        // Anyone mentioned who lives on another server. A local mention needs
        // nothing sent - they are already reading this here.
        $mentioned = DB::rows('
SELECT `Users`.`slug`, `Users`.`remoteActorURI`, `Users`.`remoteActorInboxURL`
    FROM `PostMentions`
    JOIN `Users` ON `Users`.`userId` = `PostMentions`.`userId`
    WHERE `PostMentions`.`postId` = ? AND `Users`.`remoteActorURI` IS NOT NULL
', 'RemoteRecipientData', 'i', (int) $post -> postId);

        foreach ($mentioned as $row) {
            self::addRecipient($recipients, $row);
        }

        return $recipients;
    }

    /**
     * @param array<string, array{uri: string, handle: string, inbox: string}> $recipients
     */
    private static function addRecipient(array &$recipients, ?RemoteRecipientData $row): void
    {
        if ($row === null || !is_string($row -> remoteActorURI) || $row -> remoteActorURI === '') {
            return;
        }

        // No inbox on file means nothing can be delivered there; the actor is
        // still worth addressing so other servers see who it was for.
        $recipients[$row -> remoteActorURI] = [
            'uri' => $row -> remoteActorURI,
            'handle' => '@' . (string) $row -> slug,
            'inbox' => is_string($row -> remoteActorInboxURL) ? $row -> remoteActorInboxURL : '',
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
     * The ActivityPub id of the post this one quotes: the original's own URI
     * for something that came from elsewhere, our permalink for local work.
     * Null when the quote's target has been deleted - the note goes out as
     * plain words, exactly as the page renders it.
     */
    private static function quotedURI(Post $post): ?string
    {
        if ($post -> quotedPostId === null) {
            return null;
        }

        $quoted = DB::row('
SELECT `Posts`.`postId`, `Posts`.`remoteObjectURI`, `Users`.`slug`
    FROM `Posts`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Posts`.`postId` = ?
', 'PostParentData', 'i', (int) $post -> quotedPostId);

        if ($quoted === null) {
            return null;
        }

        if (is_string($quoted -> remoteObjectURI) && $quoted -> remoteObjectURI !== '') {
            return $quoted -> remoteObjectURI;
        }

        return ServerURL::absolute('/users/' . $quoted -> slug . '/' . (int) $quoted -> postId);
    }

    /**
     * The one video or audio file a post consists of, when that is all it is.
     *
     * Only when it is the whole post: a video alongside three photos is a note
     * carrying media, not a video, and publishing it as one would leave the
     * photos with nowhere to go.
     *
     * @return array{type: string, mediaType: string, href: string}|null
     */
    private static function soleMediaItem(Post $post): ?array
    {
        $media = array_values(array_filter(
            $post -> items,
            static fn ($item): bool => $item instanceof FeedItem && in_array((string) $item -> type, ['ImageItem', 'VideoItem', 'AudioItem'], true)
        ));

        if (count($media) !== 1) {
            return null;
        }

        // The transcoder's own output formats - every video becomes mp4 and
        // every audio mp3, so these are what is actually being served.
        $kinds = [
            'VideoItem' => ['type' => 'Video', 'mediaType' => 'video/mp4'],
            'AudioItem' => ['type' => 'Audio', 'mediaType' => 'audio/mpeg'],
        ];

        $kind = $kinds[(string) $media[0] -> type] ?? null;

        return $kind === null ? null : $kind + ['href' => $media[0] -> srcURL()];
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
     * The poll half of a Question.
     *
     * The choices go under oneOf or anyOf, and which key carries them is the
     * only thing that says whether more than one may be picked - there is no
     * flag for it. Each is a Note whose name is the option's text and whose
     * replies collection states the tally, which is where every implementation
     * reads a result from.
     *
     * endTime is always present; closed appears only once it has passed, and is
     * what tells the far side to stop offering the vote.
     *
     * @return array<string, mixed>
     */
    private static function pollFields(Poll $poll): array
    {
        // The same list the page builds from, for the same reason the body is
        // rendered by DeltaRenderer rather than by something written for
        // federation: a second query would drift from the first and the two
        // copies of a result would quietly stop matching.
        $options = new PollOptionList([
            'pollId' => (int) $poll -> pollId,
            'totalVotes' => $poll -> voterCount(),
        ]);

        $choices = [];

        foreach ($options -> toJSON() as $option) {
            $choices[] = [
                'type' => 'Note',
                'name' => $option['title'],
                'replies' => [
                    'type' => 'Collection',
                    'totalItems' => $option['voteCount'],
                ],
            ];
        }

        $fields = [
            (int) $poll -> multiple === 1 ? 'anyOf' : 'oneOf' => $choices,
            'endTime' => ActivityPubActor::timestamp((string) $poll -> endsAt),
            // How many people answered, which on a multiple-choice poll is not
            // the sum of the tallies above.
            'votersCount' => $poll -> voterCount(),
        ];

        if ($poll -> isClosed()) {
            $fields['closed'] = ActivityPubActor::timestamp((string) $poll -> endsAt);
        }

        return $fields;
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
