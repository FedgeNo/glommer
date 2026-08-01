<?php

declare(strict_types=1);

/**
 * Fetching an ActivityPub document from another server, signed.
 *
 * A growing number of instances run in what Mastodon calls secure mode: they
 * will not serve an actor or an object to an unsigned request at all. Without a
 * signature those servers are simply invisible here - a follow cannot be
 * resolved, a reply cannot find its parent, and the failure looks like the
 * account not existing.
 *
 * Signed as the instance rather than as any member, which is both what the rest
 * of the network does and the private thing to do: the far side learns that this
 * server is looking, not which of its members is reading them. It also means a
 * fetch still works when it is nobody's in particular - resolving the parent of
 * an inbound reply, say.
 *
 * Falls back to an unsigned fetch when the instance has no usable key, since an
 * unsigned request still works everywhere that has not turned secure mode on.
 */
class ActivityPubFetch
{
    private const MAX_RESPONSE_BYTES = 262144;

    /**
     * @param string[] $accept
     * @return array{body: string, headers: array<string, string>}|null
     */
    public static function getJSON(string $url, array $accept = ['Accept: application/activity+json'], int $max_bytes = self::MAX_RESPONSE_BYTES): ?array
    {
        // Signed per hop rather than once for the URL asked about. A redirect
        // is a different request and the signature covers the target, so one
        // signed for the original would not verify at the destination - and an
        // instance in secure mode answers an unverifiable fetch with nothing,
        // which is the failure this exists to avoid. Every hop is a fresh
        // signature for the URL actually being requested.
        return SafeHTTPFetcher::getJSON($url, $accept, $max_bytes, self::signatureHeaders(...));
    }

    /**
     * The Host, Date and Signature a signed fetch carries, or nothing at all
     * when this instance cannot sign - in which case the request goes unsigned
     * rather than not going.
     *
     * @return string[]
     */
    private static function signatureHeaders(string $url): array
    {
        $private_key = ActivityPubKeys::privateKeyPem();
        $parts = parse_url($url);

        if ($private_key === null || $parts === false || !isset($parts['host'])) {
            return [];
        }

        // An origin-form request target is always at least "/", so a URL
        // written without one still signs the path curl will actually send
        // rather than signing nothing and failing to verify.
        $path = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
        $date = gmdate('D, d M Y H:i:s') . ' GMT';
        $key_id = ServerURL::absolute('/activitypub/actor') . '#main-key';

        // Host is sent explicitly as well as signed: the signature covers the
        // value we state, so the two have to be the same string.
        return [
            'Host: ' . $parts['host'],
            'Date: ' . $date,
            'Signature: ' . HTTPSignature::signGet($path, $parts['host'], $date, $key_id, $private_key),
        ];
    }
}
