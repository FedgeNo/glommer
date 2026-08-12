<?php

declare(strict_types=1);

/**
 * The account here for a profile address somebody else's server gave us.
 *
 * The address handed over is the one a reader sees - fedigroups.social/@startrek
 * - while the account calls itself something else underneath, usually
 * .../users/startrek. RemoteActor::ensureKnown refuses that difference on
 * purpose: an address that answers with an id belonging to somewhere else is a
 * server trying to speak for an account it does not own.
 *
 * So the difference is allowed here and the host is not: whatever the document
 * calls itself has to live on the server the address named, which is the same
 * guarantee by the part that matters.
 */
class RemoteProfileLookup
{
    public static function find(string $uri): ?User
    {
        $scheme = strtolower((string) (parse_url($uri, PHP_URL_SCHEME) ?? ''));

        if ($scheme !== 'https' && $scheme !== 'http') {
            return null;
        }

        // Already met, under the address as given.
        $known = User::byRemoteActorURI($uri);

        if ($known !== null) {
            return $known;
        }

        // A member here, handed back to us by a server that saw them.
        if (ActivityPubActor::isLocalActorURI($uri)) {
            return self::localAccount($uri);
        }

        if (RemoteServer::isBlockedURL($uri)) {
            return null;
        }

        $actor = RemoteActor::fetch($uri);

        if ($actor === null || !RemoteActor::sameHost($actor['id'], $uri)) {
            return null;
        }

        // Met under its own name rather than the one we asked with.
        $existing = User::byRemoteActorURI($actor['id']);

        if ($existing === null) {
            RemoteActor::upsert($actor);
        }

        return User::byRemoteActorURI($actor['id']);
    }

    /** The member an address here names, if it names one. */
    private static function localAccount(string $uri): ?User
    {
        $slug = trim((string) parse_url($uri, PHP_URL_PATH), '/');
        $slug = str_starts_with($slug, 'users/') ? substr($slug, 6) : ltrim($slug, '@');

        return $slug === '' ? null : User::byUsername(rawurldecode($slug));
    }
}
