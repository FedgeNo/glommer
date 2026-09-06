<?php

declare(strict_types=1);

/**
 * A local member as a Fediverse actor.
 *
 * The site-wide Application actor (ActivityPubKeys) stays where it is and keeps
 * doing its one job - being the identity behind an outbound Follow. This is the
 * other thing entirely: every member is their own Person actor, addressable as
 * user@host, followable from anywhere, with a keypair of their own so a remote
 * server verifies THEM rather than the instance they happen to live on.
 *
 * The actor URI is the profile URL already in use - /users/{slug}/ - served as
 * ActivityJSON or HTML depending on what the caller asks for. One canonical
 * address per person means a pasted profile link resolves in a browser and in a
 * Fediverse search, and nothing has to keep two URLs agreeing about who someone
 * is.
 *
 * Keys are made on demand rather than at signup, so accounts that predate
 * federation get one the first time they are dereferenced, and nothing has to
 * backfill the whole table at upgrade time.
 */
class ActivityPubActor
{
    /** Every post here is public, so this is the only audience there is. */
    public const PUBLIC_AUDIENCE = 'https://www.w3.org/ns/activitystreams#Public';

    public static function uriFor(User $user): string
    {
        return ServerURL::absolute('/users/' . $user -> slug . '/');
    }

    public static function inboxFor(User $user): string
    {
        return ServerURL::absolute('/users/' . $user -> slug . '/inbox');
    }

    public static function outboxFor(User $user): string
    {
        return ServerURL::absolute('/users/' . $user -> slug . '/outbox');
    }

    public static function followersFor(User $user): string
    {
        return ServerURL::absolute('/users/' . $user -> slug . '/followers');
    }

    public static function followingFor(User $user): string
    {
        return ServerURL::absolute('/users/' . $user -> slug . '/following');
    }

    /** Where the network looks for someone's pinned posts. */
    public static function featuredFor(User $user): string
    {
        return ServerURL::absolute('/users/' . $user -> slug . '/featured');
    }

    /** The key id a remote server sees in a signature and dereferences back to. */
    public static function keyIdFor(User $user): string
    {
        return self::uriFor($user) . '#main-key';
    }

    /**
     * The Fediverse handle for a local member: user@host, where host is our
     * canonical one rather than whatever a request arrived on.
     */
    public static function handleFor(User $user): string
    {
        return $user -> slug . '@' . self::canonicalHost();
    }

    /**
     * The handle of the instance's own Application actor - the site speaking as
     * itself rather than as any member.
     *
     * Derived from the site's configured title so nothing here is named after
     * one particular deployment. It is deliberately not the software's name:
     * that identifies what this runs, while this identifies who it is.
     *
     * Reduced to what a Fediverse local part may contain, and falling back to
     * the canonical host when a title reduces to nothing at all - a site called
     * "!!!" still needs an address.
     */
    public static function instanceUsername(): string
    {
        $title = strtolower((string) Config::get('siteTitle'));
        $slug = (string) preg_replace('/[^a-z0-9_]/', '', $title);

        if ($slug !== '') {
            return substr($slug, 0, 64);
        }

        $host = (string) preg_replace('/[^a-z0-9_]/', '', strtolower(self::canonicalHost()));

        return $host === '' ? 'site' : substr($host, 0, 64);
    }

    /**
     * Whether a username is the instance's own.
     *
     * It cannot be anybody's: WebFinger answers for the instance actor before
     * it looks for a member (see activitypub-webfinger.php), so a member
     * holding this name would be unreachable from the rest of the network -
     * their handle would resolve to the server itself, and every follow and
     * mention aimed at them would land on an actor with no profile.
     */
    public static function isInstanceUsername(string $slug): bool
    {
        return strcasecmp(trim($slug), self::instanceUsername()) === 0;
    }

    public static function canonicalHost(): string
    {
        $parts = parse_url((string) Config::get('siteURL'));

        return ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }

    /**
     * This member's public key, generating the pair on first use. Returns null
     * only when the instance has no encryption secret configured, in which case
     * a private key could not be stored safely and federation should stay off
     * rather than write one in the clear.
     */
    public static function publicKeyPem(User $user): ?string
    {
        if ($user -> remoteActorURI !== null) {
            return $user -> remoteActorPublicKeyPem;
        }

        if (is_string($user -> actorPublicKeyPem) && $user -> actorPublicKeyPem !== '') {
            return $user -> actorPublicKeyPem;
        }

        return self::generateKeypairFor($user)['publicKeyPem'] ?? null;
    }

    /**
     * This member's private key, for signing what they send. Decrypted per use
     * and never held anywhere; the ciphertext is all the database has.
     */
    public static function privateKeyPem(User $user): ?string
    {
        if ($user -> remoteActorURI !== null) {
            return null;
        }

        $stored = is_string($user -> actorEncryptedPrivateKey) ? $user -> actorEncryptedPrivateKey : '';

        if ($stored === '') {
            $generated = self::generateKeypairFor($user);

            return $generated['privateKeyPem'] ?? null;
        }

        return ActivityPubKeys::decryptPrivateKey($stored, (string) Env::get('ACTIVITYPUB_ENCRYPTION_KEY', ''));
    }

    /**
     * Makes and stores a keypair for a member who has none. Encrypted with the
     * same .env secret the instance key uses, so a database-only leak hands
     * over no signing key - anyone's.
     *
     * @return array{publicKeyPem?: string, privateKeyPem?: string}
     */
    private static function generateKeypairFor(User $user): array
    {
        if ($user -> remoteActorURI !== null || $user -> userId === null) {
            return [];
        }

        $encryption_key = (string) Env::get('ACTIVITYPUB_ENCRYPTION_KEY', '');

        try {
            $pair = ActivityPubKeys::generateKeypair();
            $encrypted = ActivityPubKeys::encryptPrivateKey($pair['privateKeyPem'], $encryption_key);
        } catch (\RuntimeException $error) {
            // No usable secret, or OpenSSL is unavailable. Federation for this
            // member simply doesn't start - storing an unencrypted key to keep
            // going would defeat the point of encrypting any of them.
            return [];
        }

        DB::run('
UPDATE `Users`
    SET `actorPublicKeyPem` = ?, `actorEncryptedPrivateKey` = ?
    WHERE `userId` = ? AND `actorPublicKeyPem` IS NULL
', 'ssi', $pair['publicKeyPem'], $encrypted, (int) $user -> userId);

        // Another request may have generated one first, in which case that one
        // is the truth - a key this process invented but did not store would
        // sign things no remote server could verify.
        $stored = DB::row('
SELECT `actorPublicKeyPem`, `actorEncryptedPrivateKey`
    FROM `Users`
    WHERE `userId` = ?
', 'User', 'i', (int) $user -> userId);

        if ($stored === null || !is_string($stored -> actorPublicKeyPem)) {
            return [];
        }

        $user -> actorPublicKeyPem = $stored -> actorPublicKeyPem;
        $user -> actorEncryptedPrivateKey = $stored -> actorEncryptedPrivateKey;

        return [
            'publicKeyPem' => $stored -> actorPublicKeyPem,
            'privateKeyPem' => ActivityPubKeys::decryptPrivateKey((string) $stored -> actorEncryptedPrivateKey, $encryption_key) ?? '',
        ];
    }

    /**
     * The actor document a remote server dereferences. Deliberately close to
     * what Mastodon publishes, since that is what the rest of the network is
     * written against.
     *
     * @return array<string, mixed>|null null when this member cannot federate
     */
    public static function document(User $user): ?array
    {
        if ($user -> remoteActorURI !== null || $user -> userId === null) {
            return null;
        }

        $public_key = self::publicKeyPem($user);

        if ($public_key === null) {
            return null;
        }

        $uri = self::uriFor($user);

        $document = [
            '@context' => [
                'https://www.w3.org/ns/activitystreams',
                'https://w3id.org/security/v1',
            ],
            'id' => $uri,
            'type' => 'Person',
            'preferredUsername' => $user -> slug,
            'name' => (string) ($user -> title !== null && $user -> title !== '' ? $user -> title : $user -> slug),
            'url' => $uri,
            'inbox' => self::inboxFor($user),
            'outbox' => self::outboxFor($user),
            'followers' => self::followersFor($user),
            'following' => self::followingFor($user),
            'featured' => self::featuredFor($user),
            'endpoints' => ['sharedInbox' => ServerURL::absolute('/activitypub/inbox')],
            'manuallyApprovesFollowers' => false,
            'discoverable' => true,
            'published' => self::timestamp((string) $user -> createdAt),
            'publicKey' => [
                'id' => self::keyIdFor($user),
                'owner' => $uri,
                'publicKeyPem' => $public_key,
            ],
        ];

        // A bio is stored as plain text here but read as HTML on the other side,
        // so it is escaped rather than handed over raw.
        if (is_string($user -> description) && $user -> description !== '') {
            $document['summary'] = '<p>' . htmlspecialchars($user -> description, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
        }

        // Both halves of a migration. movedTo says this account has left;
        // alsoKnownAs says which accounts may move onto it.
        if (is_string($user -> movedToURI) && $user -> movedToURI !== '') {
            $document['movedTo'] = $user -> movedToURI;
        }

        $aliases = ActivityPubMove::aliasesFor($user);

        if ($aliases !== []) {
            $document['alsoKnownAs'] = $aliases;
        }

        if ((int) $user -> hasAvatar === 1) {
            $document['icon'] = [
                'type' => 'Image',
                'url' => $user -> avatarURL(),
            ];
        }

        return $document;
    }

    /**
     * Whether an actor URI is one of our own members.
     *
     * Two people on the same server must never reach each other over the
     * Fediverse. They already share a feed directly, so a federated edge on top
     * would deliver every post twice - once natively and once over the wire -
     * and the reader would simply see it duplicated. Checked on the resolved
     * actor URI rather than the handle, since a personal domain can delegate to
     * whatever server actually hosts the account, and that server may be this
     * one.
     */
    public static function isLocalActorURI(string $actor_uri): bool
    {
        $host = parse_url($actor_uri, PHP_URL_HOST);

        if (!is_string($host) || $host === '') {
            return false;
        }

        $port = parse_url($actor_uri, PHP_URL_PORT);
        $authority = $host . ($port === null ? '' : ':' . $port);

        // Hostnames are case-insensitive, so a caller normalising differently
        // is naming the same server, not a different one.
        return strcasecmp($authority, self::canonicalHost()) === 0;
    }

    /**
     * The member an actor URI of ours names, or null if it names nothing here.
     *
     * Resolved by matching the URI this server would publish for that member
     * rather than by pulling a slug out of the path: the two are the same
     * string when the URI is really ours, and comparing whole URIs means a
     * path that merely looks like ours cannot be talked into resolving.
     */
    public static function localUserFromURI(string $actor_uri): ?User
    {
        if (!self::isLocalActorURI($actor_uri)) {
            return null;
        }

        $path = parse_url($actor_uri, PHP_URL_PATH);

        if (!is_string($path) || !preg_match('#\A/users/([^/]+)/\z#', $path, $matches)) {
            return null;
        }

        $user = User::byUsername(rawurldecode($matches[1]));

        if ($user === null || $user -> remoteActorURI !== null || (int) $user -> banned === 1) {
            return null;
        }

        return self::uriFor($user) === $actor_uri ? $user : null;
    }

    /** ActivityStreams wants xsd:dateTime; the DB gives a MySQL datetime. */
    public static function timestamp(string $datetime): string
    {
        $time = strtotime($datetime);

        return gmdate('Y-m-d\TH:i:s\Z', $time === false ? time() : $time);
    }

    /**
     * Whether a request wants the actor rather than the profile page. Checked
     * against the Accept header the way content negotiation is meant to work,
     * so a browser gets HTML from the same URL a remote server gets JSON from.
     */
    public static function wantsActivityJSON(string $accept_header): bool
    {
        $activity = null;
        $html = null;

        foreach (explode(',', strtolower($accept_header)) as $position => $range) {
            $parts = array_map('trim', explode(';', $range));
            $type = array_shift($parts);
            $quality = 1.0;

            foreach ($parts as $parameter) {
                if (preg_match('/\Aq\s*=\s*([0-9.]+)\z/', $parameter, $matches) === 1) {
                    $quality = max(0.0, min(1.0, (float) $matches[1]));
                    break;
                }
            }

            if (in_array($type, ['application/activity+json', 'application/ld+json'], true)) {
                if ($activity === null || $quality > $activity['quality']) {
                    $activity = ['quality' => $quality, 'position' => $position];
                }
            }

            $html_specificity = match ($type) {
                'text/html' => 2,
                'text/*' => 1,
                '*/*' => 0,
                default => null,
            };

            if ($html_specificity !== null) {
                if ($html === null
                    || $html_specificity > $html['specificity']
                    || ($html_specificity === $html['specificity'] && $quality > $html['quality'])) {
                    $html = ['quality' => $quality, 'position' => $position, 'specificity' => $html_specificity];
                }
            }
        }

        if ($activity === null || $activity['quality'] <= 0) {
            return false;
        }

        if ($html === null || $html['quality'] <= 0) {
            return true;
        }

        if ($activity['quality'] !== $html['quality']) {
            return $activity['quality'] > $html['quality'];
        }

        return $activity['position'] < $html['position'];
    }
}
