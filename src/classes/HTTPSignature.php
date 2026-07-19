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
    private const SIGNED_HEADERS = '(request-target) host date digest';

    /**
     * How far a delivery's signed Date may sit from our own clock. A
     * signature is otherwise valid forever, so anyone who captures one
     * delivery could replay it indefinitely; bounding the window bounds that.
     * Deliberately generous - federated servers drift, and a too-tight window
     * turns ordinary clock skew into silent delivery loss.
     */
    private const MAX_DATE_SKEW_SECONDS = 3600;

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

    public static function verify(string $method, string $path, string $host, string $date, string $digest, string $signature_header, string $public_key_pem): bool
    {
        $fields = self::parseSignatureHeader($signature_header);

        if ($fields === null || $fields['algorithm'] !== 'rsa-sha256' || $fields['headers'] !== self::SIGNED_HEADERS) {
            return false;
        }

        $signature_binary = base64_decode($fields['signature'], true);

        if ($signature_binary === false) {
            return false;
        }

        $signing_string = self::signingString($method, $path, $host, $date, $digest);

        return openssl_verify($signing_string, $signature_binary, $public_key_pem, OPENSSL_ALGO_SHA256) === 1;
    }

    /** @return array{keyId: string, algorithm: string, headers: string, signature: string}|null */
    public static function parseSignatureHeader(string $signature_header): ?array
    {
        $fields = [];

        foreach (['keyId', 'algorithm', 'headers', 'signature'] as $name) {
            // Anchored to a real field boundary (string start, comma, or
            // whitespace) so a parameter whose name merely ENDS with one of
            // these - x-keyId="..." - can't be picked up as that field.
            if (preg_match('/(?:\A|[,\s])' . $name . '="([^"]*)"/', $signature_header, $match) !== 1) {
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
