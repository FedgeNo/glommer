<?php

declare(strict_types=1);

/**
 * The people named in a remote post, brought here so the post can point at
 * them.
 *
 * A mention travels across the network twice: as words in the content, and as
 * a Mention entry in the activity's tag array carrying the account's real
 * address. Servers vary in what the content says - some write the whole
 * handle, some write "@alice" and leave the rest to the tag, and some anchor
 * the words themselves so there is nothing to work out.
 *
 * The bare form is the one that goes wrong. "@alice" in a post from somewhere
 * else is not @alice on this site; linked as though it were, it points at
 * whoever here happens to share the name, or at nobody at all.
 *
 * So each named account is fetched and given its shadow row before the post is
 * stored, and the mention links to that local profile - the same page every
 * other reference to a remote account here leads to, showing what this server
 * knows of them rather than sending the reader off-site.
 *
 * Fetching happens on the inbox path, which is why it is bounded: at most
 * MAX_IMPORTS accounts per post, nothing already known is fetched again, and
 * every address goes through the guarded fetcher that refuses private
 * addresses. A post naming a hundred accounts costs this server ten lookups,
 * not a hundred.
 */
class RemoteMentions
{
    /**
     * How many unknown accounts one post may cause this server to go and read.
     * Matches what a post written here may name (Mention::MAX_MENTIONS), since
     * that is what a reasonable post looks like in either direction.
     */
    private const MAX_IMPORTS = Mention::MAX_MENTIONS;

    /**
     * Handle (lowercased, no leading @) => the profile page here for that
     * account. Handles are keyed both with and without their domain, because
     * the words in the content may be either while the tag names the full one.
     *
     * @param array<string, mixed> $object the activity's object
     * @return array<string, string>
     */
    public static function localProfiles(array $object): array
    {
        $profiles = [];
        $imported = 0;

        foreach (self::mentionTags($object) as $handle => $actor_uri) {
            $user = self::localCopy($actor_uri, $imported);

            if ($user === null || !is_string($user -> slug)) {
                continue;
            }

            $url = ServerURL::absolute('/users/' . rawurlencode($user -> slug) . '/');

            $profiles[$handle] = $url;

            // The bare name as well, which is what the content usually says.
            // First tag wins where two servers' accounts share a local part:
            // the ambiguity is real, and taking the last one seen is no better
            // and less predictable.
            $local_part = explode('@', $handle)[0];

            if ($local_part !== '' && !isset($profiles[$local_part])) {
                $profiles[$local_part] = $url;
            }
        }

        return $profiles;
    }

    /**
     * The account here for one address, brought in if this server has not met
     * them. $imported counts the ones actually fetched, so the cap applies to
     * work done rather than to accounts named.
     */
    private static function localCopy(string $actor_uri, int &$imported): ?User
    {
        $known = User::byRemoteActorURI($actor_uri);

        if ($known !== null) {
            return $known;
        }

        // A local actor named from elsewhere is a member here, addressed by
        // their own address - already a row, nothing to import.
        if (ActivityPubActor::isLocalActorURI($actor_uri)) {
            return null;
        }

        if ($imported >= self::MAX_IMPORTS || RemoteServer::isBlockedURL($actor_uri)) {
            return null;
        }

        $imported++;

        return RemoteActor::ensureKnown($actor_uri);
    }

    /**
     * Handle => actor address, from the activity's Mention tags.
     *
     * @param array<string, mixed> $object
     * @return array<string, string>
     */
    private static function mentionTags(array $object): array
    {
        $tags = $object['tag'] ?? null;

        if (!is_array($tags)) {
            return [];
        }

        $found = [];

        foreach ($tags as $tag) {
            if (!is_array($tag) || ($tag['type'] ?? null) !== 'Mention') {
                continue;
            }

            $href = $tag['href'] ?? null;
            $name = $tag['name'] ?? null;

            if (!is_string($href) || !is_string($name) || $href === '') {
                continue;
            }

            $handle = strtolower(ltrim(trim($name), '@'));

            if ($handle !== '' && !isset($found[$handle])) {
                $found[$handle] = $href;
            }
        }

        return $found;
    }
}
