<?php

declare(strict_types=1);

/**
 * Processes a verified incoming ActivityPub activity (the caller - the
 * inbox endpoint - has already checked the HTTP Signature before this ever
 * runs). Scope is deliberately narrow: Accept (our own outbound Follow),
 * Create/Update of a Note, and Delete. Anything else is silently ignored,
 * not an error - a Fediverse server can send activity types this app has no
 * use for, and that's expected, not a bug to report.
 */
class ActivityPubInbox
{
    public static function process(array $activity, string $signed_actor_uri): void
    {
        match ($activity['type'] ?? null) {
            'Accept' => self::handleAccept($signed_actor_uri),
            'Create' => self::handleCreate($activity, $signed_actor_uri),
            'Update' => self::handleUpdate($activity, $signed_actor_uri),
            'Delete' => self::handleDelete($activity, $signed_actor_uri),
            default => null,
        };
    }

    private static function handleAccept(string $actor_uri): void
    {
        $accepted_status = 'accepted';

        DB::run('
UPDATE `RemoteFollows`
    SET `status` = ?
    WHERE `remoteActorURI` = ?
', 'ss', $accepted_status, $actor_uri);
    }

    private static function handleCreate(array $activity, string $actor_uri): void
    {
        $object = $activity['object'] ?? null;

        if (is_array($object) && ($object['type'] ?? null) === 'Note') {
            self::ingestNote($object, $actor_uri);
        }
    }

    private static function handleUpdate(array $activity, string $actor_uri): void
    {
        $object = $activity['object'] ?? null;

        if (!is_array($object) || ($object['type'] ?? null) !== 'Note') {
            return;
        }

        $object_uri = $object['id'] ?? null;

        if (!is_string($object_uri) || $object_uri === '') {
            return;
        }

        $post_id = self::postIdForRemoteObject($object_uri);

        if ($post_id === null || RemoteObjectTombstone::isTombstoned($object_uri)) {
            return;
        }

        $author = self::shadowUserFor($actor_uri);

        if ($author === null || $author -> banned === 1) {
            return;
        }

        [$description, $description_delta] = self::deltaFromContent(is_string($object['content'] ?? null) ? $object['content'] : '');

        DB::run('
UPDATE `Posts`
    SET `description` = ?, `descriptionDelta` = ?, `editedAt` = current_timestamp()
    WHERE `postId` = ?
', 'ssi', $description, $description_delta, $post_id);
    }

    private static function handleDelete(array $activity, string $actor_uri): void
    {
        $object = $activity['object'] ?? null;
        $object_uri = is_array($object) ? ($object['id'] ?? null) : $object;

        if (!is_string($object_uri) || $object_uri === '') {
            return;
        }

        RemoteObjectTombstone::tombstone($object_uri, 'deleted by origin server');

        $post_id = self::postIdForRemoteObject($object_uri);

        if ($post_id !== null) {
            DB::run('
DELETE FROM `Posts`
    WHERE `postId` = ?
', 'i', $post_id);
        }
    }

    private static function ingestNote(array $object, string $actor_uri): void
    {
        $object_uri = $object['id'] ?? null;

        if (!is_string($object_uri) || $object_uri === '' || RemoteObjectTombstone::isTombstoned($object_uri)) {
            return;
        }

        if (self::postIdForRemoteObject($object_uri) !== null) {
            return;
        }

        $author = self::shadowUserFor($actor_uri);

        if ($author === null || $author -> banned === 1) {
            return;
        }

        $parent_id = null;
        $in_reply_to = $object['inReplyTo'] ?? null;

        if (is_string($in_reply_to) && $in_reply_to !== '') {
            $parent_id = self::postIdForRemoteObject($in_reply_to);

            // Not in reply to anything on this site (a post we don't hold) -
            // ignored outright, per the scoping decision: no dangling replies
            // with unresolvable context.
            if ($parent_id === null) {
                return;
            }
        }

        [$description, $description_delta] = self::deltaFromContent(is_string($object['content'] ?? null) ? $object['content'] : '');

        $stmt = DB::run('
INSERT INTO `Posts` (`userId`, `parentId`, `description`, `descriptionDelta`, `remoteObjectURI`)
    VALUES (?, ?, ?, ?, ?)
', 'iisss', $author -> userId, $parent_id, $description, $description_delta, $object_uri);
        $post_id = (int) mysqli_insert_id(DB::connection());

        // Only top-level posts fan out to followers' feeds - a reply is
        // reached through the parent post's own reply list, the same as any
        // other reply, visible to whoever can already see that parent.
        if ($parent_id === null) {
            Timeline::fanOutRemotePost($actor_uri, $post_id);
        }
    }

    /** @return array{0: ?string, 1: ?string} [description, descriptionDelta] */
    private static function deltaFromContent(string $content): array
    {
        // Remote content arrives as (possibly HTML) text from an untrusted
        // server - stripped to plain text rather than trusted as markup, then
        // run through the exact same Delta::sanitize() a locally-typed post's
        // own content goes through (link-scheme safety, control-char strip).
        $plain = trim(strip_tags($content));

        if ($plain === '') {
            return [null, null];
        }

        $ops = Delta::sanitize([['insert' => $plain . "\n"]]);
        $plaintext = Delta::plainText($ops);

        if ($plaintext === '') {
            return [null, null];
        }

        return [$plaintext, json_encode(['ops' => $ops], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
    }

    private static function postIdForRemoteObject(string $remote_object_uri): ?int
    {
        $stmt = DB::run('
SELECT `postId`
    FROM `Posts`
    WHERE `remoteObjectURI` = ?
', 's', $remote_object_uri);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);

        return $row !== null ? (int) $row['postId'] : null;
    }

    private static function shadowUserFor(string $actor_uri): ?User
    {
        return DB::row('
SELECT *
    FROM `Users`
    WHERE `remoteActorURI` = ?
', 'User', 's', $actor_uri);
    }
}
