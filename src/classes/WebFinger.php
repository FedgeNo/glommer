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

            if (!is_string(parse_url($href, PHP_URL_HOST))) {
                continue;
            }

            return $href;
        }

        return null;
    }

    /**
     * Whether the actor's own host agrees that this handle belongs to it.
     *
     * A handle's domain may legitimately point at an actor elsewhere - that's
     * how a personal domain delegates to whatever server actually hosts the
     * account. But taken alone it also lets any domain name an actor it
     * doesn't own, quietly following an account the person never asked for.
     * So when the two hosts differ, the actor's own host is asked
     * independently, and only an answer that points back at the same actor is
     * accepted. A domain can claim whatever it likes about itself; it can't
     * make another server corroborate it.
     */
    public static function confirmsActor(string $actor_uri, string $preferred_username): bool
    {
        $actor_host = parse_url($actor_uri, PHP_URL_HOST);

        if (!is_string($actor_host) || $preferred_username === '') {
            return false;
        }

        $confirmed_uri = self::resolveActorURI($preferred_username, $actor_host);

        return $confirmed_uri !== null && $confirmed_uri === $actor_uri;
    }
}
