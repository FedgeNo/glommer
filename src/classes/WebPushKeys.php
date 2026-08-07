<?php

declare(strict_types=1);

/**
 * The site's one VAPID keypair - what identifies this server to the
 * browsers' push services. P-256, because that is what VAPID (RFC 8292) is.
 * Generated once by bin/install.php beside the ActivityPub keys and stored
 * the same way: the public half plain in Settings, the private half
 * encrypted under the same .env secret, so a database-only leak doesn't
 * hand over the sending identity.
 */
class WebPushKeys
{
    public const PUBLIC_SETTING = 'vapidPublicKey';
    public const PRIVATE_SETTING = 'vapidEncryptedPrivateKey';

    /** @return array{publicKeyPem: string, privateKeyPem: string} */
    public static function generateKeypair(): array
    {
        $resource = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);

        if ($resource === false) {
            throw new \RuntimeException('openssl_pkey_new() failed to generate a P-256 keypair.');
        }

        if (!openssl_pkey_export($resource, $private_key_pem)) {
            throw new \RuntimeException('openssl_pkey_export() failed to export the VAPID private key.');
        }

        $details = openssl_pkey_get_details($resource);

        if ($details === false || !isset($details['key'])) {
            throw new \RuntimeException('openssl_pkey_get_details() failed to return the VAPID public key.');
        }

        return ['publicKeyPem' => $details['key'], 'privateKeyPem' => (string) $private_key_pem];
    }

    public static function isConfigured(): bool
    {
        return (string) Settings::get(self::PUBLIC_SETTING, '') !== ''
            && (string) Settings::get(self::PRIVATE_SETTING, '') !== '';
    }

    /**
     * The public key the browser subscribes with: the uncompressed P-256
     * point, base64url - the exact string PushManager.subscribe() takes as
     * applicationServerKey.
     */
    public static function publicKeyForBrowser(): ?string
    {
        $pem = (string) Settings::get(self::PUBLIC_SETTING, '');

        if ($pem === '') {
            return null;
        }

        $details = openssl_pkey_get_details(openssl_pkey_get_public($pem));

        if ($details === false || !isset($details['ec']['x'], $details['ec']['y'])) {
            return null;
        }

        // openssl strips leading zero bytes off the coordinates; the wire
        // format is fixed-width 32 each.
        return self::base64url("\x04"
            . str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT)
            . str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT));
    }

    /** The decrypted private key, for signing VAPID tokens. */
    public static function privateKeyPem(): ?string
    {
        $stored = (string) Settings::get(self::PRIVATE_SETTING, '');

        if ($stored === '') {
            return null;
        }

        return ActivityPubKeys::decryptPrivateKey($stored, (string) Env::get('ACTIVITYPUB_ENCRYPTION_KEY', ''));
    }

    public static function base64url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    public static function base64urlDecode(string $encoded): ?string
    {
        $decoded = base64_decode(strtr($encoded, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
