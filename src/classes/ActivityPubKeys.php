<?php

declare(strict_types=1);

/**
 * The one site-wide ActivityPub signing identity (not per-user - the local
 * user who initiated a given follow is purely our own bookkeeping via
 * RemoteFollows; the remote side only ever sees "this Glommer instance").
 * The keypair itself is generated once by bin/install.php, never hand-run -
 * see ensure_activitypub_keypair() there. The private key is encrypted at
 * rest (Settings holds only ciphertext) using ACTIVITYPUB_ENCRYPTION_KEY, a
 * secret that lives in .env same as WS_SECRET - so a database-only leak (a
 * dump, a read-only SQL injection) doesn't also hand over the signing key.
 */
class ActivityPubKeys
{
    private const CIPHER = 'aes-256-gcm';

    /** AES-256 takes a 32-byte key, which is 64 hex characters. */
    private const KEY_HEX_LENGTH = 64;

    /** GCM's authentication tag, appended ahead of the ciphertext. */
    private const TAG_LENGTH = 16;

    /** @return array{publicKeyPem: string, privateKeyPem: string} */
    public static function generateKeypair(): array
    {
        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($resource === false) {
            throw new \RuntimeException('openssl_pkey_new() failed to generate an RSA keypair.');
        }

        if (!openssl_pkey_export($resource, $private_key_pem)) {
            throw new \RuntimeException('openssl_pkey_export() failed to export the generated private key (is openssl.cnf readable?).');
        }

        $details = openssl_pkey_get_details($resource);

        if ($details === false || !isset($details['key'])) {
            throw new \RuntimeException('openssl_pkey_get_details() failed to return the generated public key.');
        }

        return ['publicKeyPem' => $details['key'], 'privateKeyPem' => (string) $private_key_pem];
    }

    public static function encryptPrivateKey(string $private_key_pem, string $encryption_key_hex): string
    {
        $key = self::binaryKey($encryption_key_hex);

        if ($key === null) {
            throw new \RuntimeException('ACTIVITYPUB_ENCRYPTION_KEY must be exactly ' . self::KEY_HEX_LENGTH . ' hex characters (32 bytes).');
        }

        $iv = random_bytes((int) openssl_cipher_iv_length(self::CIPHER));

        $ciphertext = openssl_encrypt($private_key_pem, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_LENGTH);

        if ($ciphertext === false) {
            throw new \RuntimeException('Encrypting the ActivityPub private key failed.');
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Decodes the .env secret into raw key bytes, or null when it isn't a
     * usable key. The length check is the load-bearing part: OpenSSL silently
     * zero-pads an empty or short key to the cipher's block size rather than
     * refusing it, so an unset or truncated secret would otherwise "encrypt"
     * the signing key under an all-zeros key - the exact database-leak
     * scenario this encryption exists to prevent, with no visible symptom.
     */
    private static function binaryKey(string $encryption_key_hex): ?string
    {
        if (strlen($encryption_key_hex) !== self::KEY_HEX_LENGTH || preg_match('/\A[0-9a-fA-F]+\z/', $encryption_key_hex) !== 1) {
            return null;
        }

        $key = hex2bin($encryption_key_hex);

        return $key === false ? null : $key;
    }

    public static function isConfigured(): bool
    {
        return self::publicKeyPem() !== null
            && (string) Settings::get('activityPubEncryptedPrivateKey', '') !== ''
            && self::binaryKey((string) Env::get('ACTIVITYPUB_ENCRYPTION_KEY', '')) !== null;
    }

    public static function publicKeyPem(): ?string
    {
        $stored = (string) Settings::get('activityPubPublicKeyPem', '');

        return $stored === '' ? null : $stored;
    }

    public static function privateKeyPem(): ?string
    {
        $stored = (string) Settings::get('activityPubEncryptedPrivateKey', '');

        if ($stored === '') {
            return null;
        }

        return self::decryptPrivateKey($stored, (string) Env::get('ACTIVITYPUB_ENCRYPTION_KEY', ''));
    }

    public static function decryptPrivateKey(string $stored, string $encryption_key_hex): ?string
    {
        $key = self::binaryKey($encryption_key_hex);

        if ($key === null) {
            return null;
        }

        $raw = base64_decode($stored, true);
        $iv_length = (int) openssl_cipher_iv_length(self::CIPHER);

        // Anything shorter than iv+tag carries no ciphertext at all; substr
        // would hand openssl empty strings, which it reports as a plain
        // decryption failure rather than the malformed input it actually is.
        if ($raw === false || strlen($raw) <= $iv_length + self::TAG_LENGTH) {
            return null;
        }

        $iv = substr($raw, 0, $iv_length);
        $tag = substr($raw, $iv_length, self::TAG_LENGTH);
        $ciphertext = substr($raw, $iv_length + self::TAG_LENGTH);

        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);

        return $plaintext === false ? null : $plaintext;
    }
}
