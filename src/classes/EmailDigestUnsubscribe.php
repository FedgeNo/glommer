<?php

declare(strict_types=1);

/**
 * The link at the foot of a digest that turns the digests off.
 *
 * Signed rather than stored: the mail carries the member's id and a tag over
 * it, so there is no table to keep, nothing to expire, and a link still works
 * whenever the message is finally read. It authorizes exactly one change and
 * names no session, which is what lets it work for somebody who is not signed
 * in - and somebody who has stopped visiting is precisely who that is.
 *
 * No expiry on purpose. A dead unsubscribe link is worse than an old one: the
 * person is trying to make the mail stop, and the only alternative left to
 * them is to mark it as spam.
 */
class EmailDigestUnsubscribe
{
    /** How much of the HMAC the link carries. 128 bits - not guessable. */
    private const TAG_LENGTH = 32;

    /**
     * The link to put in a digest, or null on an installation with no signing
     * key - see key(). A digest is not sent at all in that case, rather than
     * sent with no way out of it.
     */
    public static function URL(int $user_id): ?string
    {
        $tag = self::tag($user_id);

        return $tag === null ? null : ServerURL::absolute('/unsubscribe?token=' . $user_id . '.' . $tag);
    }

    /** Who a token names, or null if it names nobody this server signed for. */
    public static function userIdFor(string $token): ?int
    {
        $parts = explode('.', $token, 2);

        if (count($parts) !== 2 || !ctype_digit($parts[0]) || !ctype_xdigit($parts[1])) {
            return null;
        }

        $user_id = (int) $parts[0];
        $expected = self::tag($user_id);

        if ($expected === null || !hash_equals($expected, $parts[1])) {
            return null;
        }

        return $user_id;
    }

    private static function tag(int $user_id): ?string
    {
        $key = self::key();

        return $key === null ? null : substr(hash_hmac('sha256', (string) $user_id, $key), 0, self::TAG_LENGTH);
    }

    /**
     * A key of this feature's own, derived from the server secret the same way
     * every other signed thing here derives one, so a tag from this can never
     * be made to stand in for a tag from anything else.
     */
    private static function key(): ?string
    {
        $secret_hex = (string) Env::get('ACTIVITYPUB_ENCRYPTION_KEY', '');

        if (strlen($secret_hex) !== 64 || !ctype_xdigit($secret_hex)) {
            return null;
        }

        return hash_hkdf('sha256', (string) hex2bin($secret_hex), 32, 'glommer-email-digest-unsubscribe');
    }
}
