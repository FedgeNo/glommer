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

        $preferred_username = is_string($data['preferredUsername'] ?? null) ? $data['preferredUsername'] : '';
        $name = is_string($data['name'] ?? null) ? $data['name'] : $preferred_username;
        $icon_url = is_string($data['icon']['url'] ?? null) ? $data['icon']['url'] : null;

        return [
            'id' => is_string($data['id'] ?? null) ? $data['id'] : $actor_uri,
            'inbox' => $data['inbox'],
            'publicKeyPem' => $data['publicKey']['publicKeyPem'],
            'preferredUsername' => $preferred_username,
            'name' => $name,
            'iconURL' => $icon_url,
        ];
    }
}
