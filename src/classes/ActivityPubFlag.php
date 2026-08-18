<?php

declare(strict_types=1);

/**
 * Abuse reports crossing servers.
 *
 * Moderation only works across a network if the reports cross it too. Without
 * this, someone here who sees an abusive post from another server can have it
 * hidden locally and nothing else - the account carries on unchallenged
 * everywhere else, and its own moderators never hear about it.
 *
 * Both directions are sent by the instance rather than by the person, which is
 * what the rest of the network does and the only safe choice: a report names the
 * account being reported to its own moderators, and naming the reporter as well
 * hands a harasser the identity of whoever spoke up.
 */
class ActivityPubFlag
{
    /** Reports are filed against this account, which is the site itself. */
    private const SYSTEM_REPORTER_ID = 1;

    /**
     * A report from another server about something here. Raised in the ordinary
     * moderation queue - a federated report is not a different kind of report,
     * it just arrived differently.
     */
    public static function received(array $activity, User $reporter): void
    {
        $reason = is_string($activity['content'] ?? null) ? trim($activity['content']) : '';
        $objects = $activity['object'] ?? null;
        $objects = is_array($objects) && !isset($objects['id']) ? $objects : [$objects];

        // Attributed to the reporting account so a moderator can see which
        // server is complaining, and can ban it if the complaints are the
        // abuse. Falls back to the site account when the reporter is somehow
        // not on file.
        $reporter_id = $reporter -> userId === null ? self::SYSTEM_REPORTER_ID : (int) $reporter -> userId;

        // A remote server can name several things in one report. Capped so a
        // hostile one cannot fill the queue from a single delivery.
        foreach (array_slice($objects, 0, 20) as $object) {
            $uri = is_string($object) ? $object : (is_array($object) ? ($object['id'] ?? null) : null);

            if (!is_string($uri) || $uri === '') {
                continue;
            }

            [$type, $id] = self::localTargetFor($uri);

            if ($type === null || $id === null) {
                continue;
            }

            ReportManager::create($reporter_id, $type, $id, self::describe($reason, $reporter));
        }
    }

    /**
     * Tells a remote server that one of its posts was reported here, so its own
     * moderators can act. Sent as the instance, never naming the member who
     * reported - see the class note.
     */
    public static function send(string $target_type, int $target_id, ?string $reason): void
    {
        if ($target_type !== 'post') {
            return;
        }

        $target = self::remoteTargetFor($target_id);

        if ($target === null) {
            return;
        }

        if (ActivityPubKeys::privateKeyPem() === null) {
            return;
        }

        $instance_actor = ServerURL::absolute('/activitypub/actor');

        // Queued like everything else, with no member named so the worker
        // signs it as the instance. Queued rather than posted here so a report
        // to a server that is briefly unreachable is retried instead of lost.
        FediverseDelivery::enqueue(null, [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $instance_actor . '#flags/' . bin2hex(random_bytes(8)),
            'type' => 'Flag',
            'actor' => $instance_actor,
            'content' => (string) $reason,
            'object' => [$target['objectURI'], $target['actorURI']],
        ], [$target['inboxURL']]);
    }

    /** Marks a reason as having come from elsewhere, and from whom. */
    private static function describe(string $reason, User $reporter): string
    {
        $from = $reporter -> remoteActorURI === null ? 'another server' : (string) $reporter -> slug;
        $prefix = 'Reported from the Fediverse (' . $from . ')';

        return $reason === '' ? $prefix : $prefix . ': ' . $reason;
    }

    /**
     * The local thing a URI names - a post or a member - or nulls when it names
     * neither.
     *
     * @return array{0: ?string, 1: ?int}
     */
    private static function localTargetFor(string $uri): array
    {
        if (!ActivityPubActor::isLocalActorURI($uri)) {
            return [null, null];
        }

        $path = parse_url($uri, PHP_URL_PATH);

        if (!is_string($path)) {
            return [null, null];
        }

        // A post: /users/{slug}/{id}, resolved by the inverse of the method
        // that mints those URIs in the first place, which is also what matches
        // it through its author so a URI naming the right id under the wrong
        // person resolves to nothing.
        if (preg_match('#\A/users/[^/]+/[0-9]+\z#', $path) === 1) {
            $post_id = ActivityPubNote::localPostIdFor($uri);

            return $post_id === null ? [null, null] : ['post', $post_id];
        }

        $member = ActivityPubActor::localUserFromURI($uri);

        return $member === null ? [null, null] : ['user', (int) $member -> userId];
    }

    /**
     * Where a reported post lives and where to tell its moderators, plus the
     * author's actor URI - which is what their server actually files the report
     * against.
     *
     * @return array{objectURI: string, actorURI: string, inboxURL: string}|null
     */
    private static function remoteTargetFor(int $post_id): ?array
    {
        $row = DB::row('
SELECT `Posts`.`remoteObjectURI`, `Users`.`remoteActorURI`, `Users`.`remoteActorInboxURL`
    FROM `Posts`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Posts`.`postId` = ? AND `Posts`.`remoteObjectURI` IS NOT NULL
', 'RemoteFlagTargetData', 'i', $post_id);

        if ($row === null
            || !is_string($row -> remoteObjectURI)
            || !is_string($row -> remoteActorURI)
            || !is_string($row -> remoteActorInboxURL)
            || $row -> remoteActorInboxURL === '') {
            return null;
        }

        return [
            'objectURI' => $row -> remoteObjectURI,
            'actorURI' => $row -> remoteActorURI,
            'inboxURL' => $row -> remoteActorInboxURL,
        ];
    }
}
