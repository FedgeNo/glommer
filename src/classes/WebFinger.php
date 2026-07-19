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
        // Re-validated at the boundary rather than trusting the caller: the
        // domain is interpolated straight into the lookup URL, so a value
        // carrying a slash or query character would rewrite the path itself.
        if (!URL::isValidHostname($domain)) {
            return null;
        }

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
            if (!is_array($link)) {
                continue;
            }

            $rel = $link['rel'] ?? null;
            $type = $link['type'] ?? null;
            $href = $link['href'] ?? null;

            if ($rel !== 'self' || !in_array($type, ['application/activity+json', 'application/ld+json'], true) || !is_string($href) || $href === '') {
                continue;
            }

            // The actor has to live on the host we asked. A server answering
            // for its own domain is expected; one answering with an actor
            // somewhere else is claiming to speak for a host it doesn't
            // control, which would quietly follow an account the person never
            // asked for. (This is what rules out WebFinger delegation to a
            // separate host - see the note in claude/TODO.md.)
            if (strcasecmp((string) parse_url($href, PHP_URL_HOST), $domain) !== 0) {
                continue;
            }

            return $href;
        }

        return null;
    }
}
