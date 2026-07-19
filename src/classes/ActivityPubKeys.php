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

        openssl_pkey_export($resource, $private_key_pem);
        $public_key_pem = openssl_pkey_get_details($resource)['key'];

        return ['publicKeyPem' => $public_key_pem, 'privateKeyPem' => $private_key_pem];
    }

    public static function encryptPrivateKey(string $private_key_pem, string $encryption_key_hex): string
    {
        $key = hex2bin($encryption_key_hex);
        $iv = random_bytes(openssl_cipher_iv_length(self::CIPHER));

        $ciphertext = openssl_encrypt($private_key_pem, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($ciphertext === false) {
            throw new \RuntimeException('Encrypting the ActivityPub private key failed.');
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    public static function isConfigured(): bool
    {
        return Settings::get('activityPubPublicKeyPem') !== null
            && Settings::get('activityPubEncryptedPrivateKey') !== null
            && Env::get('ACTIVITYPUB_ENCRYPTION_KEY') !== null;
    }

    public static function publicKeyPem(): ?string
    {
        return Settings::get('activityPubPublicKeyPem');
    }

    public static function privateKeyPem(): ?string
    {
        $stored = Settings::get('activityPubEncryptedPrivateKey');
        $encryption_key_hex = Env::get('ACTIVITYPUB_ENCRYPTION_KEY');

        if ($stored === null || $encryption_key_hex === null) {
            return null;
        }

        return self::decryptPrivateKey($stored, $encryption_key_hex);
    }

    public static function decryptPrivateKey(string $stored, string $encryption_key_hex): ?string
    {
        $raw = base64_decode($stored, true);

        if ($raw === false) {
            return null;
        }

        $iv_length = openssl_cipher_iv_length(self::CIPHER);
        $iv = substr($raw, 0, $iv_length);
        $tag = substr($raw, $iv_length, 16);
        $ciphertext = substr($raw, $iv_length + 16);

        $key = hex2bin($encryption_key_hex);
        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);

        return $plaintext === false ? null : $plaintext;
    }
}
