<?php

declare(strict_types=1);

/**
 * Account migration: taking your followers with you when you change servers.
 *
 * Two claims have to agree before anything happens, and that mutual check is
 * the whole security design. The account leaving says movedTo; the account
 * arriving says alsoKnownAs. A server acting on a Move verifies both, because
 * the servers that carry out a migration are other people's - if one claim were
 * enough, anyone could send a Move naming somebody else and redirect their
 * entire following, and the person losing it would have no way to intervene.
 *
 * Followers move. Posts do not. Object ids are permanent and belong to the
 * server that minted them, so a migrated account arrives with its audience and
 * an empty history. That is the protocol, not a shortcoming here.
 *
 * It also matters more here than on most servers: a username is permanent and
 * retired on deletion, so this is the only sanctioned way to change identity -
 * and it only ever works between servers, never within one.
 */
class ActivityPubMove
{
    /**
     * The aliases a member has declared - the accounts they are claiming to
     * also be, so one of those can move here.
     *
     * @return string[]
     */
    public static function aliasesFor(User $user): array
    {
        $stored = is_string($user -> alsoKnownAs) ? json_decode($user -> alsoKnownAs, true) : null;

        return is_array($stored) ? array_values(array_filter($stored, 'is_string')) : [];
    }

    /**
     * Records the aliases a member claims. Only actor URIs, and never our own -
     * an alias pointing here would be an account claiming to also be itself,
     * which nothing sensible can come of.
     *
     * @param string[] $uris
     */
    public static function setAliases(User $user, array $uris): void
    {
        $aliases = [];

        foreach ($uris as $uri) {
            $uri = trim($uri);

            if ($uri === '' || strlen($uri) > 255 || ActivityPubActor::isLocalActorURI($uri)) {
                continue;
            }

            if (!in_array(strtolower((string) parse_url($uri, PHP_URL_SCHEME)), ['http', 'https'], true)) {
                continue;
            }

            $aliases[$uri] = true;
        }

        $encoded = $aliases === [] ? null : json_encode(array_keys($aliases));

        DB::run('
UPDATE `Users`
    SET `alsoKnownAs` = ?
    WHERE `userId` = ?
', 'si', $encoded, (int) $user -> userId);
    }

    /**
     * Declares that a member has moved away, and tells their followers.
     *
     * Verified from this side too: the destination has to already claim this
     * account in its own alsoKnownAs, or the Move would be one every receiving
     * server correctly refuses, and the member would be left thinking they had
     * migrated when nobody had followed them.
     *
     * @return array{ok: bool, error: ?string}
     */
    public static function publish(User $mover, string $destination_uri): array
    {
        $words = Strings::for(self::class);
        if ($mover -> remoteActorURI !== null || $mover -> userId === null) {
            return ['ok' => false, 'error' => (string) ($words['localOnly'] ?? '')];
        }

        if (ActivityPubActor::isLocalActorURI($destination_uri)) {
            return ['ok' => false, 'error' => (string) ($words['sameServer'] ?? '')];
        }

        $destination = RemoteActor::fetch($destination_uri);

        if ($destination === null) {
            return ['ok' => false, 'error' => (string) ($words['fetchFailed'] ?? '')];
        }

        $mover_uri = ActivityPubActor::uriFor($mover);

        if (!in_array($mover_uri, $destination['alsoKnownAs'], true)) {
            return ['ok' => false, 'error' => str_replace('{uri}', $mover_uri, (string) ($words['aliasMissing'] ?? ''))];
        }

        DB::run('
UPDATE `Users`
    SET `movedToURI` = ?
    WHERE `userId` = ?
', 'si', $destination['id'], (int) $mover -> userId);

        $mover -> movedToURI = $destination['id'];

        FediverseDelivery::fanOut($mover, [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $mover_uri . '#moves/' . bin2hex(random_bytes(8)),
            'type' => 'Move',
            'actor' => $mover_uri,
            'object' => $mover_uri,
            'target' => $destination['id'],
        ]);

        return ['ok' => true, 'error' => null];
    }

    /**
     * Somebody a member here follows has moved. Their followers here are moved
     * with them, which is the entire point - otherwise the posts simply stop
     * arriving and it looks like the person went quiet.
     */
    public static function received(array $activity, User $mover): void
    {
        $target = $activity['target'] ?? null;
        $target = is_string($target) ? $target : (is_array($target) ? ($target['id'] ?? null) : null);

        $object = $activity['object'] ?? null;
        $object = is_string($object) ? $object : (is_array($object) ? ($object['id'] ?? null) : null);

        if (!is_string($target) || $mover -> remoteActorURI === null) {
            return;
        }

        // The activity must be about the account that signed it. A server may
        // only move its own.
        if (is_string($object) && $object !== $mover -> remoteActorURI) {
            return;
        }

        // Never inward: an account elsewhere cannot move itself onto this
        // server by announcing it. Arriving here is a local account declaring
        // the alias, which is the other half of this class.
        if (ActivityPubActor::isLocalActorURI($target)) {
            return;
        }

        $destination = RemoteActor::fetch($target);

        // The half that makes this safe. Without it, any server could redirect
        // anyone's followers by sending a Move naming them.
        if ($destination === null || !in_array($mover -> remoteActorURI, $destination['alsoKnownAs'], true)) {
            return;
        }

        RemoteActor::upsert($destination);

        $followers = DB::rows('
SELECT `localUserId`
    FROM `RemoteFollows`
    WHERE `remoteActorURI` = ?
', 'RemoteFollowData', 's', $mover -> remoteActorURI);

        foreach ($followers as $follow) {
            $local_user_id = (int) $follow -> localUserId;

            // Followed first, unfollowed second: if the new follow fails to
            // deliver, the member is left following the old account rather than
            // neither, which is the better of the two failures.
            RemoteFollow::createForActor($local_user_id, $destination['id']);
            RemoteFollow::remove($local_user_id, (string) $mover -> remoteActorURI);
        }
    }
}
