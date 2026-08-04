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
 */
class MessageFranking
{
    public static function isConfigured(): bool
    {
        return self::key() !== null;
    }

    public static function tag(int $sender_id, int $recipient_id, string $envelope): ?string
    {
        $key = self::key();

        if ($key === null) {
            return null;
        }

        // The ids are bound in so a tag only verifies against the row it was
        // issued for - the same ciphertext claimed between two other people
        // is a different message.
        return hash_hmac('sha256', $sender_id . ':' . $recipient_id . ':' . $envelope, $key);
    }

    public static function verify(int $sender_id, int $recipient_id, string $envelope, string $tag): bool
    {
        $expected = self::tag($sender_id, $recipient_id, $envelope);

        return $expected !== null && hash_equals($expected, $tag);
    }

    private static function key(): ?string
    {
        $secret_hex = (string) Env::get('ACTIVITYPUB_ENCRYPTION_KEY', '');

        if (strlen($secret_hex) !== 64 || preg_match('/\A[0-9a-fA-F]+\z/', $secret_hex) !== 1) {
            return null;
        }

        return hash_hkdf('sha256', (string) hex2bin($secret_hex), 32, 'glommer-message-franking');
    }
}
