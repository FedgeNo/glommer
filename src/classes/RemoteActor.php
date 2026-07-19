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

    /** Far above any real RSA/Ed25519 PEM, and well inside the TEXT column that holds it. */
    private const MAX_PUBLIC_KEY_LENGTH = 8192;

    /** @return array{id: string, inbox: string, publicKeyPem: string, preferredUsername: string, name: string, iconURL: ?string}|null */
    public static function fetch(string $actor_uri): ?array
    {
        $response = SafeHTTPFetcher::getJSON($actor_uri, ['Accept: application/activity+json'], self::MAX_RESPONSE_BYTES);

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

        return [
            'id' => $id,
            'inbox' => $data['inbox'],
            'publicKeyPem' => $data['publicKey']['publicKeyPem'],
            'preferredUsername' => mb_substr($preferred_username, 0, self::MAX_DISPLAY_NAME_LENGTH),
            'name' => mb_substr($name, 0, self::MAX_DISPLAY_NAME_LENGTH),
            'iconURL' => $icon_url,
        ];
    }

    /**
     * Whether two URLs live on the same host. Both must be real http(s) URLs
     * with a host - anything unparseable is "not the same", so a malformed
     * value can never pass by both sides failing to parse.
     */
    private static function sameHost(string $url, string $other_url): bool
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
