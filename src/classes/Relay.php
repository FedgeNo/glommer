<?php

declare(strict_types=1);

/**
 * A shared firehose this server subscribes to.
 *
 * Federation is follow-shaped: a server nobody follows from receives nothing,
 * which leaves a new instance looking at an empty room with no way to discover
 * anyone. A relay answers that - subscribe, and every public post from every
 * other subscribed server arrives, while this server's go out to all of them.
 *
 * What arrives is a firehose, and it is treated as one: relayed posts go to a
 * feed of their own (RelayFeedList) rather than into the main feed, because
 * nobody here asked for any of them. Subscribing is an admin's explicit act and
 * there is no default relay - the load is whatever the subscribed servers
 * happen to publish, which is nobody's to decide on an admin's behalf.
 *
 * The subscription is the instance's, not a member's: it is this server that
 * joins, and the Follow is signed by the instance actor accordingly.
 */
class Relay
{
    /** Which form a subscribing Follow may name. See the schema note. */
    public const FOLLOW_PUBLIC = 'public';
    public const FOLLOW_ACTOR = 'actor';

    public const PUBLIC_URI = 'https://www.w3.org/ns/activitystreams#Public';

    /**
     * Whether any relay is subscribed at all, kept as a setting so the
     * navigation can ask on every page without reading the table. Most servers
     * never subscribe to one, and a permanent link to a feed that can only be
     * empty is furniture in everybody's way.
     */
    private const ANY_SETTING = 'hasRelays';

    // Declared so a row fetched via DB::row()/DB::rows() doesn't set them as
    // deprecated dynamic properties.
    public ?int $relayId = null;
    public ?string $actorURI = null;
    public ?string $inboxURL = null;
    public ?string $followActivityId = null;
    public ?string $followObject = null;
    public ?string $status = null;
    public ?string $createdAt = null;

    /** Whether this server subscribes to any relay, answered from settings. */
    public static function anySubscribed(): bool
    {
        return Settings::get(self::ANY_SETTING) === '1';
    }

    /** @return self[] every relay, newest first */
    public static function all(): array
    {
        return DB::rows('
SELECT *
    FROM `Relays`
    ORDER BY `relayId` DESC
', self::class);
    }

    public static function byActorURI(string $actor_uri): ?self
    {
        return DB::row('
SELECT *
    FROM `Relays`
    WHERE `actorURI` = ?
', self::class, 's', $actor_uri);
    }

    /**
     * Whether this actor is a relay whose subscription is live. Pending ones
     * are excluded deliberately: until the far side has answered, anything
     * arriving from it is unsolicited, and taking its posts on the strength of
     * a request we merely sent would let anyone push a firehose here by
     * announcing at an address an admin once typed.
     */
    public static function isSubscribed(string $actor_uri): bool
    {
        $relay = self::byActorURI($actor_uri);

        return $relay !== null && $relay -> status === 'accepted';
    }

    /**
     * The inboxes every public activity from this server also goes to. A relay
     * stands in for a crowd of followers, so it is fed exactly what a follower
     * would be.
     *
     * @return string[]
     */
    public static function deliveryInboxes(): array
    {
        $accepted = 'accepted';

        $rows = DB::rows('
SELECT `inboxURL`
    FROM `Relays`
    WHERE `status` = ?
', self::class, 's', $accepted);

        return array_map(static fn (self $relay): string => (string) $relay -> inboxURL, $rows);
    }

    /**
     * Subscribes to the relay at $actor_uri. Returns null on success, or why
     * it could not be done - the admin typed an address and is owed a reason
     * rather than a page that looks the same as before.
     */
    public static function subscribe(string $actor_uri, string $follow_object): ?string
    {
        $words = Strings::for(self::class);
        if (!URL::isValidHTTPURL($actor_uri)) {
            return (string) ($words['invalidAddress'] ?? '');
        }

        if (!in_array($follow_object, [self::FOLLOW_PUBLIC, self::FOLLOW_ACTOR], true)) {
            return (string) ($words['unknownStyle'] ?? '');
        }

        if (RemoteServer::isBlockedURL($actor_uri)) {
            return (string) ($words['blocked'] ?? '');
        }

        if (self::byActorURI($actor_uri) !== null) {
            return (string) ($words['alreadySubscribed'] ?? '');
        }

        if (ActivityPubKeys::privateKeyPem() === null) {
            return (string) ($words['noSigningKey'] ?? '');
        }

        $actor = RemoteActor::fetch($actor_uri);

        if ($actor === null) {
            return (string) ($words['notActor'] ?? '');
        }

        $instance_actor = ServerURL::absolute('/activitypub/actor');
        $follow_activity_id = $instance_actor . '#follows/' . bin2hex(random_bytes(8));
        $pending = 'pending';

        DB::run('
INSERT INTO `Relays` (`actorURI`, `inboxURL`, `followActivityId`, `followObject`, `status`)
    VALUES (?, ?, ?, ?, ?)
', 'sssss', $actor['id'], $actor['inbox'], $follow_activity_id, $follow_object, $pending);

        // Queued rather than sent here, so a relay that happens to be down is
        // retried instead of leaving a subscription that was never asked for.
        FediverseDelivery::enqueue(null, [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $follow_activity_id,
            'type' => 'Follow',
            'actor' => $instance_actor,
            'object' => $follow_object === self::FOLLOW_ACTOR ? $actor['id'] : self::PUBLIC_URI,
        ], [$actor['inbox']]);

        Settings::set(self::ANY_SETTING, '1');

        return null;
    }

    /**
     * Withdraws a subscription. The Undo names whatever the Follow named, or
     * the far side has no way to match it to what it granted.
     */
    public static function unsubscribe(string $actor_uri): bool
    {
        $relay = self::byActorURI($actor_uri);

        if ($relay === null) {
            return false;
        }

        $instance_actor = ServerURL::absolute('/activitypub/actor');

        FediverseDelivery::enqueue(null, [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $instance_actor . '#undos/' . bin2hex(random_bytes(8)),
            'type' => 'Undo',
            'actor' => $instance_actor,
            'object' => [
                'id' => $relay -> followActivityId,
                'type' => 'Follow',
                'actor' => $instance_actor,
                'object' => $relay -> followObject === self::FOLLOW_ACTOR ? $relay -> actorURI : self::PUBLIC_URI,
            ],
        ], [(string) $relay -> inboxURL]);

        // The posts it brought stay - they have been read and replied to, and
        // dropping a subscription is a reason to stop new ones arriving, not to
        // make the old ones vanish. RelayPosts.relayId goes null with the row.
        DB::run('
DELETE
    FROM `Relays`
    WHERE `relayId` = ?
', 'i', $relay -> relayId);

        self::rememberWhetherAnyRemain();

        return true;
    }

    /** The far side granted the subscription, so its posts start counting. */
    public static function accepted(int $relay_id): void
    {
        $accepted = 'accepted';

        DB::run('
UPDATE `Relays`
    SET `status` = ?
    WHERE `relayId` = ?
', 'si', $accepted, $relay_id);
    }

    /**
     * Refused. Removed rather than parked, the same as a refused follow: a
     * subscription that is never coming is not a state worth showing, and
     * subscribing again is what asking again should mean.
     */
    public static function rejected(int $relay_id): void
    {
        DB::run('
DELETE
    FROM `Relays`
    WHERE `relayId` = ?
', 'i', $relay_id);

        self::rememberWhetherAnyRemain();
    }

    /** Keeps the navigation's cheap answer in step with the table. */
    private static function rememberWhetherAnyRemain(): void
    {
        $result = mysqli_stmt_get_result(DB::run('
SELECT COUNT(*) AS `count`
    FROM `Relays`
'));

        Settings::set(self::ANY_SETTING, ((int) mysqli_fetch_assoc($result)['count']) > 0 ? '1' : '0');
    }

    /**
     * The relay row an Accept or Reject answers, matched on the activity id
     * recorded when the Follow was sent. Without that, any server could grant
     * itself a subscription here by asserting an answer nobody asked for.
     */
    public static function answering(string $actor_uri, string $follow_activity_id): ?self
    {
        return DB::row('
SELECT *
    FROM `Relays`
    WHERE `actorURI` = ? AND `followActivityId` = ?
', self::class, 'ss', $actor_uri, $follow_activity_id);
    }

    /** Records that a post arrived through a relay rather than through a follow. */
    public static function recordPost(int $post_id, int $relay_id): void
    {
        DB::run('
INSERT IGNORE INTO `RelayPosts` (`postId`, `relayId`)
    VALUES (?, ?)
', 'ii', $post_id, $relay_id);
    }
}
