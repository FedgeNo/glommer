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

        $author = self::shadowUserFor($actor_uri);

        if ($author === null || $author -> banned === 1) {
            return;
        }

        $post = self::postAuthoredBy($object_uri, (int) $author -> userId);

        if ($post === null || RemoteObjectTombstone::isTombstoned($object_uri)) {
            return;
        }

        [$description, $description_delta] = self::deltaFromContent(is_string($object['content'] ?? null) ? $object['content'] : '');

        DB::run('
UPDATE `Posts`
    SET `description` = ?, `descriptionDelta` = ?, `editedAt` = current_timestamp()
    WHERE `postId` = ?
', 'ssi', $description, $description_delta, $post -> postId);
    }

    private static function handleDelete(array $activity, string $actor_uri): void
    {
        $object = $activity['object'] ?? null;
        $object_uri = is_array($object) ? ($object['id'] ?? null) : $object;

        if (!is_string($object_uri) || $object_uri === '') {
            return;
        }

        $author = self::shadowUserFor($actor_uri);

        if ($author === null) {
            return;
        }

        // Scoped to a post this actor actually wrote. Acting on any URI the
        // sender names would let one followed account delete another's posts
        // here - and, because a tombstone is permanent, pre-emptively block a
        // post it names from ever being ingested in the first place.
        $post = self::postAuthoredBy($object_uri, (int) $author -> userId);

        if ($post === null) {
            return;
        }

        // Post::delete() rather than a bare row delete: it also clears
        // notifications pointing at the post and the media files belonging to
        // any local reply underneath it, and records the tombstone that stops
        // the origin server's next redelivery from recreating this.
        Post::delete((int) $post -> postId);
    }

    private static function ingestNote(array $object, string $actor_uri): void
    {
        $object_uri = $object['id'] ?? null;

        if (!is_string($object_uri) || !self::isStorableObjectURI($object_uri) || RemoteObjectTombstone::isTombstoned($object_uri)) {
            return;
        }

        if (self::postIdForRemoteObject($object_uri) !== null) {
            return;
        }

        // A note that names a different author than the account that signed
        // for it is refused rather than filed under the signer: accepting it
        // would let one actor claim another's object URI, and since that URI
        // is unique, permanently block the real note from ever arriving.
        // Only enforced when it's actually stated - some servers leave it off
        // the embedded object, and the signer is the right attribution then.
        $attributed_to = $object['attributedTo'] ?? null;

        if (is_string($attributed_to) && $attributed_to !== '' && $attributed_to !== $actor_uri) {
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

    /** Posts.description is a TEXT column; MySQL runs strict, so an oversized value errors rather than truncating. */
    private const MAX_DESCRIPTION_BYTES = 65535;

    /** @return array{0: ?string, 1: ?string} [description, descriptionDelta] */
    private static function deltaFromContent(string $content): array
    {
        // Remote content arrives as HTML from an untrusted server. It's
        // reduced to plain text rather than trusted as markup, then run
        // through the same Delta::sanitize() a locally-typed post goes
        // through. Block boundaries become newlines FIRST - stripping the
        // tags alone would run every paragraph of a post together into one
        // unreadable line - and entities are decoded afterwards, so an
        // ordinary "&amp;" reads as "&" instead of showing its escape.
        // Decoding last is also what keeps it safe: the result is only ever
        // used as text (a Delta insert renders as a text node, and the
        // plaintext copy is escaped again on output), so a decoded "<" is
        // inert rather than markup.
        $with_breaks = preg_replace('#<br\s*/?>|</(?:p|div|li|h[1-6]|blockquote|pre|tr)\s*>#i', "\n", $content);
        $plain = html_entity_decode(strip_tags((string) $with_breaks), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Invalid UTF-8 from a remote server would be rejected by the utf8mb4
        // columns and break json_encode; drop the bad bytes rather than 500.
        $plain = mb_convert_encoding($plain, 'UTF-8', 'UTF-8');

        // Collapse the runs of blank lines the block-to-newline pass leaves
        // behind (nested block tags each contribute one), then trim.
        $plain = trim((string) preg_replace('/\n{3,}/', "\n\n", $plain));

        if ($plain === '') {
            return [null, null];
        }

        $ops = Delta::sanitize([['insert' => $plain . "\n"]]);
        $plaintext = Delta::plainText($ops);

        if ($plaintext === '') {
            return [null, null];
        }

        // mb_strcut, not substr: cuts on a byte budget without splitting a
        // multi-byte character in half.
        if (strlen($plaintext) > self::MAX_DESCRIPTION_BYTES) {
            $plaintext = mb_strcut($plaintext, 0, self::MAX_DESCRIPTION_BYTES, 'UTF-8');
            $ops = Delta::sanitize([['insert' => $plaintext . "\n"]]);
        }

        $delta_json = json_encode(['ops' => $ops], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($delta_json === false) {
            return [null, null];
        }

        return [$plaintext, $delta_json];
    }

    /** Posts.remoteObjectURI and the tombstone table's key are both varchar(255). */
    private const MAX_OBJECT_URI_LENGTH = 255;

    /**
     * Whether an object URI can actually be stored. A longer-than-column
     * value would abort the insert under strict mode as an uncaught database
     * exception rather than a declined delivery, so it's rejected here where
     * the untrusted value arrives.
     */
    private static function isStorableObjectURI(string $object_uri): bool
    {
        if ($object_uri === '' || strlen($object_uri) > self::MAX_OBJECT_URI_LENGTH) {
            return false;
        }

        return in_array(strtolower((string) parse_url($object_uri, PHP_URL_SCHEME)), ['http', 'https'], true)
            && is_string(parse_url($object_uri, PHP_URL_HOST));
    }

    private static function postIdForRemoteObject(string $remote_object_uri): ?int
    {
        $post = DB::row('
SELECT `postId`
    FROM `Posts`
    WHERE `remoteObjectURI` = ?
', 'Post', 's', $remote_object_uri);

        return $post !== null ? (int) $post -> postId : null;
    }

    /**
     * The post at this object URI, but only when the given shadow account is
     * the one that authored it - the authorization gate for any activity that
     * mutates existing content, so a delivery can only ever act on its own
     * sender's posts.
     */
    private static function postAuthoredBy(string $remote_object_uri, int $author_user_id): ?Post
    {
        return DB::row('
SELECT `postId`, `userId`
    FROM `Posts`
    WHERE `remoteObjectURI` = ? AND `userId` = ?
', 'Post', 'si', $remote_object_uri, $author_user_id);
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
