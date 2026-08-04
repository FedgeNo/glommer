<?php

declare(strict_types=1);

/**
 * Message franking: as the server relays an encrypted message it commits to
 * the ciphertext with an HMAC under a server-held key, stored alongside the
 * message. When the recipient later reports it, the tag is re-verified before
 * the revealed per-message key is used - so a report can only ever be about a
 * ciphertext that genuinely passed through here, and a fabricated
 * "conversation" can't be planted in the moderation queue.
 *
 * The key is derived (HKDF, its own info string) from ACTIVITYPUB_ENCRYPTION_KEY
 * rather than being a second .env secret: it needs exactly the same property -
 * held outside the database so a DB-only leak doesn't yield it - and
 * derivation gives an independent key without another piece of installer
 * plumbing to generate, document, and lose.
 *
 * A tag records which key made it, because that key can be rotated. Rotating
 * it would otherwise make every message franked under the old one permanently
 * unreportable, and the failure would read as tampering rather than as a key
 * change. Retire a key by moving it to ACTIVITYPUB_ENCRYPTION_KEY_PREVIOUS
 * (comma-separated, oldest last) and old tags keep verifying; drop it from
 * there and those messages become unreportable, deliberately and knowingly.
 */
class MessageFranking
{
    /** How much of a key's fingerprint identifies it in a stored tag. */
    private const FINGERPRINT_LENGTH = 16;

    public static function isConfigured(): bool
    {
        return self::currentKey() !== null;
    }

    public static function tag(int $sender_id, int $recipient_id, string $envelope): ?string
    {
        $key = self::currentKey();

        if ($key === null) {
            return null;
        }

        return self::fingerprint($key) . ':' . self::sign($sender_id, $recipient_id, $envelope, $key);
    }

    public static function verify(int $sender_id, int $recipient_id, string $envelope, string $tag): bool
    {
        // A tag with no key named in it predates versioning, so it can only
        // have come from the key in use at the time - which is the current one
        // unless it has since been rotated, in which case it is one of the
        // retired ones and is found the same way.
        if (!str_contains($tag, ':')) {
            foreach (self::keys() as $key) {
                if (hash_equals(self::sign($sender_id, $recipient_id, $envelope, $key), $tag)) {
                    return true;
                }
            }

            return false;
        }

        [$fingerprint, $signature] = explode(':', $tag, 2);

        foreach (self::keys() as $key) {
            if (hash_equals(self::fingerprint($key), $fingerprint)) {
                return hash_equals(self::sign($sender_id, $recipient_id, $envelope, $key), $signature);
            }
        }

        return false;
    }

    /**
     * The ids are bound in so a tag only verifies against the row it was
     * issued for - the same ciphertext claimed between two other people is a
     * different message.
     */
    private static function sign(int $sender_id, int $recipient_id, string $envelope, string $key): string
    {
        return hash_hmac('sha256', $sender_id . ':' . $recipient_id . ':' . $envelope, $key);
    }

    /** Names a key without being usable as one. */
    private static function fingerprint(string $key): string
    {
        return substr(hash('sha256', 'glommer-franking-key/' . $key), 0, self::FINGERPRINT_LENGTH);
    }

    /**
     * Every key a stored tag might have been made under: the one in use, then
     * any that have been retired.
     *
     * @return string[]
     */
    private static function keys(): array
    {
        $keys = [];
        $current = self::currentKey();

        if ($current !== null) {
            $keys[] = $current;
        }

        foreach (explode(',', (string) Env::get('ACTIVITYPUB_ENCRYPTION_KEY_PREVIOUS', '')) as $retired) {
            $derived = self::derive(trim($retired));

            if ($derived !== null) {
                $keys[] = $derived;
            }
        }

        return $keys;
    }

    private static function currentKey(): ?string
    {
        return self::derive((string) Env::get('ACTIVITYPUB_ENCRYPTION_KEY', ''));
    }

    private static function derive(string $secret_hex): ?string
    {
        if (strlen($secret_hex) !== 64 || preg_match('/\A[0-9a-fA-F]+\z/', $secret_hex) !== 1) {
            return null;
        }

        return hash_hkdf('sha256', (string) hex2bin($secret_hex), 32, 'glommer-message-franking');
    }
}
