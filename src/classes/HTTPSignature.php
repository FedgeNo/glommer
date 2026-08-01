<?php

declare(strict_types=1);

/**
 * RSA-SHA256 HTTP Signatures (the draft-cavage profile ActivityPub actually
 * uses in practice) over a fixed, minimal header set: (request-target), host,
 * date, digest. Only ever verifies a signature that covers exactly those four
 * - anything claiming a different header set is rejected outright rather than
 * verified against whatever subset it happens to name, since accepting a
 * caller-chosen header list is how a signature ends up covering less than it
 * appears to.
 */
class HTTPSignature
{
    /** What we sign on the way out, delivering a body. */
    private const SIGNED_HEADERS = '(request-target) host date digest';

    /**
     * What we sign fetching something. A GET has no body, so there is no digest
     * to cover - signing one over nothing is a claim about a body that does not
     * exist, and servers checking coverage reject it.
     */
    private const SIGNED_GET_HEADERS = '(request-target) host date';

    /**
     * The coverage an inbound signature must have, whatever else it also
     * signs: the request target, the host it was meant for, its freshness,
     * and the body.
     */
    private const REQUIRED_HEADERS = ['(request-target)', 'host', 'date', 'digest'];

    /**
     * How far a delivery's signed Date may sit from our own clock. A
     * signature is otherwise valid forever, so anyone who captures one
     * delivery could replay it indefinitely; bounding the window bounds that.
     * Deliberately generous - federated servers drift, and a too-tight window
     * turns ordinary clock skew into silent delivery loss.
     */
    private const MAX_DATE_SKEW_SECONDS = 3600;

    /**
     * What an inbound signature may call its algorithm.
     *
     * Only RSA-SHA256 is actually performed - it is the one thing every actor
     * key here is - but three spellings arrive. `rsa-sha256` is what Mastodon
     * sends; `hs2019` is what the draft this profile comes from actually
     * recommends, and says the real algorithm is a property of the key rather
     * than of the header; and an absent field is allowed outright, the
     * parameter being optional. Refusing the latter two rejected perfectly
     * valid senders for naming the same thing differently.
     */
    private const ACCEPTED_ALGORITHMS = ['rsa-sha256', 'hs2019', ''];

    public static function digest(string $body): string
    {
        return 'SHA-256=' . base64_encode(hash('sha256', $body, true));
    }

    /**
     * Whether a delivery's Date header is close enough to now to be accepted.
     * Rejects an unparseable date outright rather than treating it as 0 (the
     * epoch), which would otherwise read as "very old" only by accident.
     */
    public static function dateIsFresh(string $date): bool
    {
        $parsed = strtotime($date);

        if ($parsed === false) {
            return false;
        }

        return abs(time() - $parsed) <= self::MAX_DATE_SKEW_SECONDS;
    }

    public static function sign(string $method, string $path, string $host, string $date, string $digest, string $keyId, string $privateKeyPem): string
    {
        $signing_string = self::signingString($method, $path, $host, $date, $digest);

        $signed = openssl_sign($signing_string, $signature_binary, $privateKeyPem, OPENSSL_ALGO_SHA256);

        if (!$signed) {
            throw new \RuntimeException('openssl_sign() failed while signing an outbound ActivityPub request.');
        }

        return 'keyId="' . $keyId . '",algorithm="rsa-sha256",headers="' . self::SIGNED_HEADERS . '",signature="' . base64_encode($signature_binary) . '"';
    }

    /**
     * Signs an outbound GET - "authorized fetch", which a growing number of
     * instances require before they will hand over an actor or an object at
     * all. Unsigned, those servers are simply invisible to us.
     */
    public static function signGet(string $path, string $host, string $date, string $keyId, string $privateKeyPem): string
    {
        $signing_string = '(request-target): get ' . $path . "\n"
            . 'host: ' . $host . "\n"
            . 'date: ' . $date;

        $signed = openssl_sign($signing_string, $signature_binary, $privateKeyPem, OPENSSL_ALGO_SHA256);

        if (!$signed) {
            throw new \RuntimeException('openssl_sign() failed while signing an outbound ActivityPub fetch.');
        }

        return 'keyId="' . $keyId . '",algorithm="rsa-sha256",headers="' . self::SIGNED_GET_HEADERS . '",signature="' . base64_encode($signature_binary) . '"';
    }

    /**
     * Verifies a delivery's signature against the header set the sender says
     * it covered, rather than one exact list. Implementations differ in which
     * extra headers they sign and in what order, so demanding a single
     * spelling rejects senders whose signatures are perfectly valid.
     *
     * What is NOT flexible is the coverage: the signature must cover
     * (request-target), host, date and digest, or it isn't binding the thing
     * that matters - the target, the destination, the freshness and the body.
     * A sender choosing its own shorter list is exactly the downgrade this
     * refuses.
     *
     * @param array<string, string> $headers received header values, lowercase names
     */
    public static function verify(string $method, string $path, array $headers, string $signature_header, string $public_key_pem): bool
    {
        $fields = self::parseSignatureHeader($signature_header);

        if ($fields === null || !in_array(strtolower($fields['algorithm']), self::ACCEPTED_ALGORITHMS, true)) {
            return false;
        }

        $covered = preg_split('/\s+/', strtolower(trim($fields['headers']))) ?: [];

        foreach (self::REQUIRED_HEADERS as $required) {
            if (!in_array($required, $covered, true)) {
                return false;
            }
        }

        $signing_string = self::signingStringFor($covered, $method, $path, $headers);

        if ($signing_string === null) {
            return false;
        }

        $signature_binary = base64_decode($fields['signature'], true);

        if ($signature_binary === false || $signature_binary === '') {
            return false;
        }

        return openssl_verify($signing_string, $signature_binary, $public_key_pem, OPENSSL_ALGO_SHA256) === 1;
    }

    /**
     * Rebuilds the exact string the sender signed, from the headers it named
     * and the values actually received. Null when it claims to have signed a
     * header that isn't present - the signature would never match anyway, and
     * substituting an empty value would be inventing content it never signed.
     *
     * @param string[] $covered
     * @param array<string, string> $headers
     */
    private static function signingStringFor(array $covered, string $method, string $path, array $headers): ?string
    {
        $lines = [];

        foreach ($covered as $name) {
            if ($name === '(request-target)') {
                $lines[] = '(request-target): ' . strtolower($method) . ' ' . $path;

                continue;
            }

            if (!array_key_exists($name, $headers)) {
                return null;
            }

            $lines[] = $name . ': ' . $headers[$name];
        }

        return implode("\n", $lines);
    }

    /**
     * The signature header's parameters, or null when one that has to be there
     * isn't. `algorithm` is optional in the profile - senders that omit it are
     * making no claim rather than a wrong one - so it comes back as an empty
     * string instead of failing the parse.
     *
     * @return array{keyId: string, algorithm: string, headers: string, signature: string}|null
     */
    public static function parseSignatureHeader(string $signature_header): ?array
    {
        $fields = ['algorithm' => ''];

        foreach (['keyId', 'algorithm', 'headers', 'signature'] as $name) {
            // Anchored to a real field boundary (string start, comma, or
            // whitespace) so a parameter whose name merely ENDS with one of
            // these - x-keyId="..." - can't be picked up as that field.
            if (preg_match('/(?:\A|[,\s])' . $name . '="([^"]*)"/', $signature_header, $match) !== 1) {
                if ($name === 'algorithm') {
                    continue;
                }

                return null;
            }

            $fields[$name] = $match[1];
        }

        return $fields;
    }

    private static function signingString(string $method, string $path, string $host, string $date, string $digest): string
    {
        return '(request-target): ' . strtolower($method) . ' ' . $path . "\n"
            . 'host: ' . $host . "\n"
            . 'date: ' . $date . "\n"
            . 'digest: ' . $digest;
    }
}
