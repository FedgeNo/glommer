<?php

declare(strict_types=1);

/**
 * Fetches and parses a remote account's ActivityPub actor document - the
 * profile a WebFinger lookup points at, carrying its inbox URL and the
 * public key we verify its future deliveries against.
 */
class RemoteActor
{
    private const MAX_RESPONSE_BYTES = 262144;

    /** Users.remoteActorURI is varchar(255) - a longer id can't be stored, so it's refused rather than silently cut. */
    private const MAX_ACTOR_URI_LENGTH = 255;

    /** Users.title is varchar(100), counted in characters on utf8mb4. */
    private const MAX_DISPLAY_NAME_LENGTH = 100;

    /** Users.slug is varchar(255), and a shadow slug is the whole handle. */
    private const MAX_SLUG_LENGTH = 255;

    /** Far above any real RSA/Ed25519 PEM, and well inside the TEXT column that holds it. */
    private const MAX_PUBLIC_KEY_LENGTH = 8192;

    /** Users.description is TEXT, and a bio is a paragraph or two either way. */
    private const MAX_SUMMARY_LENGTH = 2000;

    /** Matches Users.remoteActorIconURL. */
    private const MAX_ICON_URL_LENGTH = 500;

    /**
     * The actor types that mean nobody is typing.
     *
     * Self-declared, and standard: Mastodon's "this is a bot account"
     * checkbox publishes Service rather than Person, and every implementation
     * that has the idea spells it the same way. Application is an instance
     * speaking as itself. Taken at its word - claiming to be a bot gains
     * nobody anything, and nothing here rests on it but what trending reads.
     */
    public const AUTOMATED_TYPES = ['Service', 'Application'];

    /** @return array{id: string, inbox: string, sharedInbox: ?string, publicKeyPem: string, preferredUsername: string, name: string, iconURL: ?string, alsoKnownAs: string[], actorType: ?string}|null */
    public static function fetch(string $actor_uri): ?array
    {
        // Signed: an instance in secure mode will not hand over an actor to an
        // unsigned request at all, and the failure looks exactly like the
        // account not existing.
        $response = ActivityPubFetch::getJSON($actor_uri, ['Accept: application/activity+json'], self::MAX_RESPONSE_BYTES);

        if ($response === null) {
            return null;
        }

        $data = json_decode($response['body'], true);

        if (
            !is_array($data)
            || !isset($data['inbox'], $data['publicKey']['publicKeyPem'])
            || !is_string($data['inbox'])
            || !is_string($data['publicKey']['publicKeyPem'])
        ) {
            return null;
        }

        $id = is_string($data['id'] ?? null) && $data['id'] !== '' ? $data['id'] : $actor_uri;

        // The document's self-declared id decides which account this key gets
        // stored against, so a server is only allowed to speak for its own
        // host. Without this, any server could hand back an id belonging to an
        // account elsewhere together with its own key, and take over that
        // account's identity here - including overwriting the cached key of an
        // account already followed and trusted.
        if (!self::sameHost($id, $actor_uri)) {
            return null;
        }

        // The inbox is where our signed Follow gets delivered; keeping it on
        // the actor's own host stops a document from pointing our signed
        // requests at an unrelated server.
        if (!self::sameHost($data['inbox'], $id)) {
            return null;
        }

        if (strlen($id) > self::MAX_ACTOR_URI_LENGTH || strlen($data['publicKey']['publicKeyPem']) > self::MAX_PUBLIC_KEY_LENGTH) {
            return null;
        }

        $preferred_username = is_string($data['preferredUsername'] ?? null) ? $data['preferredUsername'] : '';
        $name = is_string($data['name'] ?? null) && $data['name'] !== '' ? $data['name'] : $preferred_username;
        $icon_url = is_string($data['icon']['url'] ?? null) ? $data['icon']['url'] : null;

        // The batching endpoint, where the server offers one - many followers
        // on the same instance collapse to a single delivery through it. Held
        // to the actor's own host for the same reason the inbox is: a document
        // must not be able to aim our signed requests at an unrelated server.
        $shared_inbox = $data['endpoints']['sharedInbox'] ?? null;
        $shared_inbox = is_string($shared_inbox) && self::sameHost($shared_inbox, $id) && strlen($shared_inbox) <= self::MAX_ACTOR_URI_LENGTH
            ? $shared_inbox
            : null;

        // The aliases this account claims. Half of a migration handshake: a
        // Move is only acted on when the destination independently names the
        // account that says it is leaving.
        $also_known_as = $data['alsoKnownAs'] ?? [];
        $also_known_as = array_values(array_filter(
            is_array($also_known_as) ? $also_known_as : [$also_known_as],
            static fn ($alias): bool => is_string($alias) && $alias !== ''
        ));

        return [
            'id' => $id,
            'inbox' => $data['inbox'],
            'sharedInbox' => $shared_inbox,
            'alsoKnownAs' => $also_known_as,
            'publicKeyPem' => $data['publicKey']['publicKeyPem'],
            'preferredUsername' => mb_substr($preferred_username, 0, self::MAX_DISPLAY_NAME_LENGTH),
            'name' => mb_substr($name, 0, self::MAX_DISPLAY_NAME_LENGTH),
            'iconURL' => self::iconURL($icon_url),
            'summary' => self::summaryText($data['summary'] ?? null),
            'fields' => RemoteActorFields::fromAttachments($data['attachment'] ?? null),
            'actorType' => is_string($data['type'] ?? null) ? mb_substr($data['type'], 0, 20) : null,
        ];
    }

    /**
     * The account's bio as words.
     *
     * It arrives as HTML, and Users.description is plain text everywhere else -
     * a bio typed here is plain text that the renderer linkifies. So this is
     * read as the markup it is and reduced to the words and line breaks in it,
     * which is what the same renderer then treats identically to a local one.
     */
    private static function summaryText(mixed $summary): ?string
    {
        if (!is_string($summary) || trim($summary) === '') {
            return null;
        }

        $text = trim(Delta::plainText(HTMLToDelta::convert($summary)));

        return $text === '' ? null : mb_substr($text, 0, self::MAX_SUMMARY_LENGTH);
    }

    /**
     * The avatar's address, held to the same rule every other URL out of an
     * actor document is: it is fetched later on a member's behalf, so it has to
     * be an http(s) address and no longer than the column keeping it.
     */
    private static function iconURL(?string $url): ?string
    {
        if ($url === null || strlen($url) > self::MAX_ICON_URL_LENGTH) {
            return null;
        }

        $scheme = strtolower((string) (parse_url($url, PHP_URL_SCHEME) ?? ''));

        return $scheme === 'https' || $scheme === 'http' ? $url : null;
    }

    /**
     * The local shadow row for a remote account, fetching and storing it if
     * this server has not met them before.
     *
     * Inbound federation needs this: someone following a member here is by
     * definition an actor we have never seen, and their key has to come from
     * somewhere before their signature can be checked. It comes from their own
     * server, over the guarded fetcher, and only for an id that server is
     * entitled to speak for - which is what fetch() already enforces.
     */
    public static function ensureKnown(string $actor_uri): ?User
    {
        $existing = User::byRemoteActorURI($actor_uri);

        if ($existing !== null && $existing -> remoteActorPublicKeyPem !== null) {
            return $existing;
        }

        // Never our own members: a local actor is served from here, and going
        // out to the network to ask about one would be this server interviewing
        // itself.
        if (ActivityPubActor::isLocalActorURI($actor_uri)) {
            return null;
        }

        $actor = self::fetch($actor_uri);

        if ($actor === null || $actor['id'] !== $actor_uri) {
            return null;
        }

        self::upsert($actor);

        return User::byRemoteActorURI($actor_uri);
    }

    /**
     * Re-reads an actor this server already knows, replacing what it holds -
     * display name, avatar, inbox, and the public key its deliveries are
     * verified against.
     *
     * The key is the reason this exists. A remote server rotating its signing
     * key is routine, and obligatory after one is exposed; against a cached
     * copy every delivery that follows fails verification, and it fails the
     * same way forever, because nothing else here ever asks again. That account
     * simply goes quiet, and no amount of retrying on their side changes it.
     *
     * Fetched from the actor's own server rather than believed from the
     * delivery that prompted it: the request that says a key has changed is
     * signed with the key being replaced, so trusting the document inside it
     * would let a stolen key install a new one and keep the account. Going back
     * to the source means the far server has to still be serving that key for
     * it to be adopted here.
     */
    public static function refresh(string $actor_uri): ?User
    {
        if (ActivityPubActor::isLocalActorURI($actor_uri)) {
            return null;
        }

        $actor = self::fetch($actor_uri);

        // Only ever the account that was asked about. fetch() already refuses a
        // document whose id belongs to another host; this refuses one that
        // renames itself within the same host, so a refresh can never write
        // over a different row than the one being refreshed.
        if ($actor === null || $actor['id'] !== $actor_uri) {
            return null;
        }

        self::upsert($actor);

        return User::byRemoteActorURI($actor_uri);
    }

    /**
     * Re-reads the shadow accounts this server holds that are missing the
     * inbox a message would be delivered to, and fills it in.
     *
     * A row can end up that way when it was created before this server learned
     * to record an inbox, or when the fetch that created it half-succeeded.
     * Nothing surfaces the gap - the account looks ordinary, offers a Message
     * button like any other, and the message simply has nowhere to go - so it
     * is repaired rather than waited on.
     *
     * Fetches one actor per row, so it belongs in the installer or a
     * deliberate run, never in a request.
     *
     * @return array{repaired: int, failed: int}
     */
    public static function repairMissingInboxes(): array
    {
        $no_inbox = '';

        $incomplete = DB::rows('
SELECT `remoteActorURI`
    FROM `Users`
    WHERE `remoteActorURI` IS NOT NULL
        AND (`remoteActorInboxURL` IS NULL OR `remoteActorInboxURL` = ?)
', 'User', 's', $no_inbox);

        $repaired = 0;
        $failed = 0;

        foreach ($incomplete as $shadow) {
            $actor = self::fetch((string) $shadow -> remoteActorURI);

            if ($actor === null || $actor['inbox'] === '') {
                $failed++;

                continue;
            }

            self::upsert($actor);
            $repaired++;
        }

        return ['repaired' => $repaired, 'failed' => $failed];
    }

    /** How many actors one backfill pass reads. A trickle, not a crawl of the network. */
    private const TYPES_PER_PASS = 20;

    /**
     * Fills in what kind of account a shadow row is, for the rows recorded
     * before that was kept.
     *
     * One signed fetch per account and several hundred accounts to get
     * through, so it runs a handful at a time from the federation worker
     * rather than all at once from anywhere: the point of knowing is to keep
     * bots out of trending, and trending recomputes on a timer, so there is
     * nothing waiting on this finishing quickly.
     *
     * A fetch that fails leaves the row null and it will be tried again on a
     * later pass. That is the right way round - an account this server cannot
     * currently read stays in the corpus rather than being guessed at.
     *
     * Picked at random, which is the whole reason this drains. Shadow rows
     * arrive from a relay in host order, so deleted accounts cluster: an
     * instance that answers 410 Gone for a hundred of them sits at the head of
     * an unordered queue and is handed back every pass, and nothing behind it
     * is ever read. Randomly, a permanently dead host costs the odd fetch
     * instead of the whole backfill.
     *
     * @return int how many were filled in
     */
    public static function backfillMissingTypes(): int
    {
        $filled = 0;

        foreach (self::unknownTypeURIs(self::TYPES_PER_PASS) as $actor_uri) {
            if (self::refresh($actor_uri) !== null) {
                $filled++;
            }
        }

        return $filled;
    }

    /**
     * Which accounts to ask about next. Its own method so the choosing can be
     * tested without the fetching.
     *
     * @return string[]
     */
    private static function unknownTypeURIs(int $limit): array
    {
        $rows = DB::rows('
SELECT `remoteActorURI`
    FROM `Users`
    WHERE `remoteActorURI` IS NOT NULL AND `remoteActorType` IS NULL
    ORDER BY RAND()
    LIMIT ?
', 'User', 'i', $limit);

        return array_map(static fn (User $row): string => (string) $row -> remoteActorURI, $rows);
    }

    /**
     * Writes a fetched actor into the shadow row that represents them here,
     * creating it the first time. The inbox URLs are stored so publishing does
     * not have to dereference the actor again for every post.
     *
     * @param array{id: string, inbox: string, sharedInbox: ?string, publicKeyPem: string, preferredUsername: string, name: string, iconURL: ?string, actorType: ?string} $actor
     */
    public static function upsert(array $actor): void
    {
        $existing = User::byRemoteActorURI($actor['id']);

        $display_name = $actor['name'] !== '' ? $actor['name'] : $actor['preferredUsername'];

        $actor_type = $actor['actorType'] ?? null;

        // The profile is replaced wholesale rather than merged: what the actor
        // document says now is what the account is now, and a bio or a field
        // somebody removed there has to disappear here too.
        $fields = RemoteActorFields::encode($actor['fields'] ?? []);

        if ($existing !== null) {
            DB::run('
UPDATE `Users`
    SET `title` = ?, `description` = ?, `remoteActorPublicKeyPem` = ?, `remoteActorInboxURL` = ?, `remoteActorSharedInboxURL` = ?, `remoteActorType` = ?, `remoteActorIconURL` = ?, `remoteActorFields` = ?
    WHERE `userId` = ?
', 'ssssssssi', $display_name, $actor['summary'] ?? null, $actor['publicKeyPem'], $actor['inbox'], $actor['sharedInbox'], $actor_type, $actor['iconURL'] ?? null, $fields, $existing -> userId);

            return;
        }

        $slug = self::uniqueShadowSlug($actor['preferredUsername'], $actor['id']);
        // Unroutable by construction (.invalid, RFC 2606) - a shadow row has no
        // mailbox and must never be mailed. Named for this server so two
        // instances sharing a database would not collide.
        $synthetic_email = 'remote+' . substr(sha1($actor['id']), 0, 32) . '@' . ActivityPubActor::canonicalHost() . '.invalid';
        $unusable_hash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);

        DB::run('
INSERT INTO `Users` (`slug`, `email`, `passwordHash`, `title`, `description`, `remoteActorURI`, `remoteActorPublicKeyPem`, `remoteActorInboxURL`, `remoteActorSharedInboxURL`, `remoteActorType`, `remoteActorIconURL`, `remoteActorFields`, `verified`)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
', 'ssssssssssssi', $slug, $synthetic_email, $unusable_hash, $display_name, $actor['summary'] ?? null, $actor['id'], $actor['publicKeyPem'], $actor['inbox'], $actor['sharedInbox'], $actor_type, $actor['iconURL'] ?? null, $fields, 1);
    }

    /**
     * A remote account's slug is its Fediverse handle as-is, minus the leading
     * @: bob@mastodon.social. That makes the profile URL the handle, so it
     * needs no decoding to display and no folding that could make two different
     * handles collide.
     *
     * It also cannot collide with a local username or be squatted by one:
     * api/signup.php strips local names to [a-z0-9_], so an @ is impossible
     * there, which keeps the two namespaces structurally disjoint.
     */
    private static function uniqueShadowSlug(string $preferred_username, string $actor_uri): string
    {
        $local_part = (string) preg_replace('/[^a-z0-9_]/', '', strtolower($preferred_username));
        $host = strtolower((string) parse_url($actor_uri, PHP_URL_HOST));

        if ($local_part === '' || $host === '') {
            $local_part = $local_part !== '' ? $local_part : 'user';
            $host = $host !== '' ? $host : 'unknown';
        }

        $base = substr($local_part . '@' . $host, 0, self::MAX_SLUG_LENGTH);
        $candidate = $base;

        for ($attempt = 0; $attempt < 50; $attempt++) {
            $existing = DB::row('
SELECT `slug`
    FROM `Users`
    WHERE `slug` = ?
', 'User', 's', $candidate);

            if ($existing === null) {
                return $candidate;
            }

            // Only reachable when the handle itself doesn't fit the column and
            // two different handles truncate to the same thing; a full handle
            // is unique on its own.
            $candidate = substr($base, 0, self::MAX_SLUG_LENGTH - 6) . random_int(1000, 999999);
        }

        return substr($base, 0, self::MAX_SLUG_LENGTH - 12) . bin2hex(random_bytes(6));
    }

    /**
     * Whether two URLs live on the same host. Both must be real http(s) URLs
     * with a host - anything unparseable is "not the same", so a malformed
     * value can never pass by both sides failing to parse.
     */
    public static function sameHost(string $url, string $other_url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        $other_host = parse_url($other_url, PHP_URL_HOST);

        if (!is_string($host) || !is_string($other_host) || $host === '' || $other_host === '') {
            return false;
        }

        if (!in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            return false;
        }

        return strcasecmp($host, $other_host) === 0;
    }
}
