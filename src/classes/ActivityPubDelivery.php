<?php

declare(strict_types=1);

/**
 * Putting one signed activity into one remote inbox.
 *
 * The signature is what identifies the sender - there is no other
 * authentication in ActivityPub - so this signs as whoever is sending: a
 * member's own key for their own posts, the instance key for the instance's own
 * Follow. Whose key signs is the caller's decision, because it is the same
 * decision as who the activity is from.
 *
 * Everything goes out through SafeHTTPFetcher, so an inbox URL that resolves
 * somewhere private cannot be used to make this server fetch its own network.
 */
class ActivityPubDelivery
{
    private const MAX_RESPONSE_BYTES = 65536;

    /**
     * @param array<string, mixed> $activity
     */
    public static function post(string $inbox_url, array $activity, string $key_id, string $private_key_pem): bool
    {
        $body = json_encode($activity, JSON_UNESCAPED_SLASHES);
        $parts = parse_url($inbox_url);

        // An actor id inside the activity came from a remote document, so
        // encoding can genuinely fail on invalid UTF-8 - signing false here
        // would be a TypeError rather than a failed delivery.
        if ($body === false || $parts === false || !isset($parts['host'], $parts['path'])) {
            return false;
        }

        $path = $parts['path'] . (isset($parts['query']) ? '?' . $parts['query'] : '');
        $date = gmdate('D, d M Y H:i:s') . ' GMT';
        $digest = HTTPSignature::digest($body);

        $signature = HTTPSignature::sign('POST', $path, $parts['host'], $date, $digest, $key_id, $private_key_pem);

        $headers = [
            'Host: ' . $parts['host'],
            'Date: ' . $date,
            'Digest: ' . $digest,
            'Signature: ' . $signature,
            'Content-Type: application/activity+json',
            'Accept: application/activity+json',
        ];

        return SafeHTTPFetcher::postJSON($inbox_url, $body, $headers, self::MAX_RESPONSE_BYTES) !== null;
    }

    /**
     * Sends an activity as a local member, signed with their own key. Used for
     * the small immediate sends - an Accept answering a Follow, which the far
     * side is waiting on - rather than the queued fan-out of a post.
     *
     * @param array<string, mixed> $activity
     */
    public static function postAs(User $author, string $inbox_url, array $activity): bool
    {
        $private_key = ActivityPubActor::privateKeyPem($author);

        if ($private_key === null) {
            return false;
        }

        return self::post($inbox_url, $activity, ActivityPubActor::keyIdFor($author), $private_key);
    }
}
