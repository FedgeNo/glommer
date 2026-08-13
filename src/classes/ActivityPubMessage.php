<?php

declare(strict_types=1);

/**
 * Direct messages crossing servers.
 *
 * A federated DM is a Note addressed to exactly one actor, with the public
 * collection deliberately absent from its audience. That absence is the only
 * thing marking it private - there is no separate mechanism, and no encryption
 * anywhere in ActivityPub.
 *
 * Which is why these are not as private as a message between two members here.
 * A local message is readable by one server operator; a federated one is
 * readable by two, since the receiving server stores it in the clear the same
 * way this one does. The interface says so in the thread rather than leaving
 * people to assume otherwise - see MessageList.
 */
class ActivityPubMessage
{
    /**
     * Sends a message to a remote account. Signed by the sender, addressed to
     * the recipient alone.
     */
    public static function publish(int $message_id, User $sender, User $recipient, string $body): void
    {
        if ($sender -> remoteActorURI !== null
            || $sender -> userId === null
            || $recipient -> remoteActorURI === null
            || !is_string($recipient -> remoteActorInboxURL)
            || $recipient -> remoteActorInboxURL === '') {
            return;
        }

        $sender_uri = ActivityPubActor::uriFor($sender);
        $object_uri = ServerURL::absolute('/messages/' . $message_id);

        $note = [
            'id' => $object_uri,
            'type' => 'Note',
            'attributedTo' => $sender_uri,
            // Expanded here too: this path never touches DeltaRenderer, and
            // unexpanded a shortcode would reach the far side as literal text.
            'content' => '<p>' . htmlspecialchars(EmojiShortcode::expand($body), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>',
            'published' => ActivityPubActor::timestamp(date('Y-m-d H:i:s')),
            // The recipient and nobody else. No public collection, no
            // followers - that omission is what makes it a direct message.
            'to' => [$recipient -> remoteActorURI],
            'tag' => [[
                'type' => 'Mention',
                'name' => '@' . $recipient -> slug,
                'href' => $recipient -> remoteActorURI,
            ]],
        ];

        FediverseDelivery::enqueue((int) $sender -> userId, [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $object_uri . '#create',
            'type' => 'Create',
            'actor' => $sender_uri,
            'to' => $note['to'],
            'object' => $note,
        ], [$recipient -> remoteActorInboxURL]);
    }

    /**
     * Whether an inbound object is a direct message rather than a post.
     *
     * The test is the absence of a public audience, not the presence of a
     * mention: a public post can mention someone too, and treating that as a
     * DM would file somebody's public writing into a private thread.
     */
    public static function isDirect(array $object, array $activity): bool
    {
        $audience = array_merge(
            self::addressList($object['to'] ?? null),
            self::addressList($object['cc'] ?? null),
            self::addressList($activity['to'] ?? null),
            self::addressList($activity['cc'] ?? null)
        );

        if ($audience === []) {
            return false;
        }

        foreach ($audience as $address) {
            // Addressed to the public, or to somebody's followers, means it is
            // not private however few people it names.
            if ($address === ActivityPubActor::PUBLIC_AUDIENCE || str_ends_with($address, '/followers')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Files an inbound direct message. Only ever between the account that
     * signed for it and a member here who is actually addressed.
     */
    public static function received(array $object, array $activity, User $sender): void
    {
        $object_uri = $object['id'] ?? null;

        if (!is_string($object_uri) || $object_uri === '' || strlen($object_uri) > 255 || $sender -> userId === null) {
            return;
        }

        // The message's id has to belong to the server that signed for it, the
        // same rule an inbound post is held to. The URI is what deduplicates a
        // redelivery, so a server allowed to name one on someone else's host
        // could permanently suppress a message it has no part in.
        if (!is_string($sender -> remoteActorURI) || !RemoteActor::sameHost($object_uri, $sender -> remoteActorURI)) {
            return;
        }

        if ((int) $sender -> banned === 1 || self::alreadyHave($object_uri)) {
            return;
        }

        $recipient = self::localRecipient($object, $activity);

        if ($recipient === null || $recipient -> userId === null) {
            return;
        }

        // The same wall a local message hits. A block here has to stop a
        // federated message as surely as it stops a local one.
        if (Block::exists((int) $sender -> userId, (int) $recipient -> userId)) {
            return;
        }

        $body = self::plainText(is_string($object['content'] ?? null) ? $object['content'] : '');

        if ($body === '') {
            return;
        }

        DB::run('
INSERT INTO `Messages` (`senderId`, `recipientId`, `body`, `remoteObjectURI`)
    VALUES (?, ?, ?, ?)
', 'iiss', (int) $sender -> userId, (int) $recipient -> userId, $body, $object_uri);

        $message_id = (int) mysqli_insert_id(DB::connection());

        Notification::create((int) $recipient -> userId, (int) $sender -> userId, 'message');

        WebSocketPusher::push((int) $recipient -> userId, [
            'event' => 'message',
            'message' => [
                'messageId' => $message_id,
                'senderId' => (int) $sender -> userId,
                'recipientId' => (int) $recipient -> userId,
                'body' => $body,
                'createdAt' => date('Y-m-d H:i:s'),
                'sender' => [
                    'slug' => $sender -> slug,
                    'title' => $sender -> title,
                    'image' => $sender -> avatarURL(),
                ],
            ],
        ]);
    }

    /**
     * A message body is plain text here, while the wire carries HTML - the
     * shared flattening, then the cap. Messages.body is TEXT - 65535 bytes,
     * which is what api/send-message enforces for a local message too.
     */
    private static function plainText(string $html): string
    {
        // Cut on a character boundary, not a byte one. The column counts bytes,
        // so the limit is in bytes - but a cut through the middle of a
        // multi-byte character makes a string the database refuses outright,
        // and the delivery carrying it fails rather than arriving shortened.
        return mb_strcut(RemoteHTML::toPlainText($html), 0, 65535);
    }

    /** The one member here this message is addressed to, if exactly one is. */
    private static function localRecipient(array $object, array $activity): ?User
    {
        $addresses = array_merge(
            self::addressList($object['to'] ?? null),
            self::addressList($activity['to'] ?? null)
        );

        foreach ($addresses as $address) {
            $user = ActivityPubActor::localUserFromURI($address);

            if ($user !== null) {
                return $user;
            }
        }

        return null;
    }

    /**
     * An audience field is a URI, or a list of them, or an object with an id.
     *
     * @return string[]
     */
    private static function addressList(mixed $value): array
    {
        if (is_string($value)) {
            return [$value];
        }

        if (!is_array($value)) {
            return [];
        }

        $addresses = [];

        foreach ($value as $entry) {
            if (is_string($entry)) {
                $addresses[] = $entry;
            } elseif (is_array($entry) && is_string($entry['id'] ?? null)) {
                $addresses[] = $entry['id'];
            }
        }

        return $addresses;
    }

    /** A server re-sending a message it already sent must not duplicate it. */
    private static function alreadyHave(string $object_uri): bool
    {
        return DB::row('
SELECT `messageId`
    FROM `Messages`
    WHERE `remoteObjectURI` = ?
', 'Message', 's', $object_uri) !== null;
    }
}
