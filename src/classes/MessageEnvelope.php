<?php

declare(strict_types=1);

/**
 * The stored form of an end-to-end encrypted message: a JSON envelope built
 * client-side (HTMLObjects.js's MessageCrypto) that this server relays and stores but cannot
 * open. Fields, all base64:
 *
 *   v   - format version (1)
 *   iv  - AES-GCM nonce for the body
 *   ct  - the body, encrypted under a random per-message key with the GCM tag
 *         appended (WebCrypto's native shape)
 *   kiv - AES-GCM nonce for the wrapped key
 *   wk  - that per-message key, encrypted under the conversation key the two
 *         participants derive by ECDH
 *
 * The per-message key is the piece that makes reporting compatible with
 * encryption: revealing it opens exactly one message, never the conversation
 * key and never the rest of the thread. See api/report.php.
 */
class MessageEnvelope
{
    /** AES-GCM's standard 96-bit nonce. */
    private const NONCE_LENGTH = 12;

    /** GCM's authentication tag, appended after the ciphertext. */
    private const TAG_LENGTH = 16;

    /** A raw AES-256 key (32 bytes) plus its GCM tag. */
    private const WRAPPED_KEY_LENGTH = 48;

    /** The plaintext cap messages already have, plus the GCM tag. */
    private const MAX_CIPHERTEXT_LENGTH = 65535 + self::TAG_LENGTH;

    /**
     * Validates a client-sent envelope and returns the canonical string to
     * store (and frank), or null when it isn't a well-formed envelope.
     * Re-encoded from the allowlisted fields rather than stored as received,
     * so nothing rides along and the franking tag binds to exactly what the
     * database holds.
     */
    public static function normalize(string $envelope_json): ?string
    {
        $fields = json_decode($envelope_json, true);

        if (!is_array($fields) || ($fields['v'] ?? null) !== 1) {
            return null;
        }

        $lengths = [
            'iv' => [self::NONCE_LENGTH, self::NONCE_LENGTH],
            'ct' => [self::TAG_LENGTH + 1, self::MAX_CIPHERTEXT_LENGTH],
            'kiv' => [self::NONCE_LENGTH, self::NONCE_LENGTH],
            'wk' => [self::WRAPPED_KEY_LENGTH, self::WRAPPED_KEY_LENGTH],
        ];

        $canonical = ['v' => 1];

        foreach ($lengths as $field => [$minimum, $maximum]) {
            $decoded = is_string($fields[$field] ?? null) ? base64_decode($fields[$field], true) : false;

            if ($decoded === false || strlen($decoded) < $minimum || strlen($decoded) > $maximum) {
                return null;
            }

            $canonical[$field] = base64_encode($decoded);
        }

        return (string) json_encode($canonical);
    }

    /**
     * Opens an envelope with a revealed per-message key - the moderation path,
     * and the only circumstance under which this server ever decrypts one.
     * GCM authenticates as it decrypts, so a key that "works" proves it is the
     * key this ciphertext was really encrypted under: a reporter cannot reveal
     * a different key and have a fabricated body come out.
     */
    public static function decryptWithKey(string $envelope_json, string $key): ?string
    {
        if (strlen($key) !== 32) {
            return null;
        }

        $fields = json_decode($envelope_json, true);

        if (!is_array($fields)) {
            return null;
        }

        $iv = base64_decode((string) ($fields['iv'] ?? ''), true);
        $ct = base64_decode((string) ($fields['ct'] ?? ''), true);

        if ($iv === false || $ct === false || strlen($ct) <= self::TAG_LENGTH) {
            return null;
        }

        $plaintext = openssl_decrypt(
            substr($ct, 0, -self::TAG_LENGTH),
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            substr($ct, -self::TAG_LENGTH)
        );

        return $plaintext === false ? null : $plaintext;
    }
}
