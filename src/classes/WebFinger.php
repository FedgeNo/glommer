<?php

declare(strict_types=1);

/**
 * Resolves a Fediverse handle (user@domain) to that account's ActivityPub
 * actor URI via the standard WebFinger lookup every Fediverse server exposes.
 */
class WebFinger
{
    private const MAX_RESPONSE_BYTES = 65536;

    public static function resolveActorURI(string $user, string $domain): ?string
    {
        $url = 'https://' . $domain . '/.well-known/webfinger?resource=' . urlencode('acct:' . $user . '@' . $domain);

        $response = SafeHTTPFetcher::getJSON($url, ['Accept: application/jrd+json, application/json'], self::MAX_RESPONSE_BYTES);

        if ($response === null) {
            return null;
        }

        $data = json_decode($response['body'], true);

        if (!is_array($data) || !isset($data['links']) || !is_array($data['links'])) {
            return null;
        }

        foreach ($data['links'] as $link) {
            $rel = $link['rel'] ?? null;
            $type = $link['type'] ?? null;
            $href = $link['href'] ?? null;

            if ($rel === 'self' && in_array($type, ['application/activity+json', 'application/ld+json'], true) && is_string($href) && $href !== '') {
                return $href;
            }
        }

        return null;
    }
}
