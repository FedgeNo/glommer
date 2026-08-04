<?php

declare(strict_types=1);

/**
 * The name a link preview's image is staged under before the post that will
 * own it exists. It carries who staged it, so the two endpoints that act on a
 * staged file - discarding it, or attaching it to a new post - can refuse one
 * that belongs to somebody else.
 *
 * The owner is carried rather than stored: half the name is random, and the
 * other half is an HMAC over that random half and the staging member's id. The
 * id is never recoverable from the name, but a name can be checked against the
 * member presenting it, which is the only question either endpoint asks. That
 * keeps a transient file out of the database entirely, and keeps the name the
 * same 32 hex characters it has always been.
 *
 * Derived from ACTIVITYPUB_ENCRYPTION_KEY (its own info string, so it is an
 * independent key) for the same reason MessageFranking is: it has to live
 * outside the database. With no key configured there is nothing to sign with,
 * so seeds go back to being unguessable-but-unowned, which is what they were
 * before this existed.
 */
class StagedUploadSeed
{
    private const PREFIX = 'lp-';

    /** Half the name each: 8 random bytes, then 8 bytes of tag. */
    private const HALF_LENGTH = 16;

    public static function issue(int $user_id): string
    {
        $nonce = bin2hex(random_bytes(8));
        $tag = self::tag($nonce, $user_id);

        // With no key to sign with the second half is random instead, so the
        // name is the same shape either way - the upload pipeline names files
        // by it, and a shorter one would land somewhere nothing looks.
        return self::PREFIX . $nonce . ($tag !== '' ? $tag : bin2hex(random_bytes(8)));
    }

    /**
     * Whether this member staged this file. A malformed name is nobody's; an
     * unsigned installation accepts any well-formed one.
     */
    public static function belongsTo(string $seed, int $user_id): bool
    {
        if (preg_match('/^lp-([a-f0-9]{16})([a-f0-9]{16})$/', $seed, $match) !== 1) {
            return false;
        }

        $expected = self::tag($match[1], $user_id);

        return $expected === '' ? true : hash_equals($expected, $match[2]);
    }

    /** Empty when no key is configured, which turns the check off. */
    private static function tag(string $nonce, int $user_id): string
    {
        $key = self::key();

        return $key === null ? '' : substr(hash_hmac('sha256', $nonce . ':' . $user_id, $key), 0, self::HALF_LENGTH);
    }

    private static function key(): ?string
    {
        $secret_hex = (string) Env::get('ACTIVITYPUB_ENCRYPTION_KEY', '');

        if (strlen($secret_hex) !== 64 || preg_match('/\A[0-9a-fA-F]+\z/', $secret_hex) !== 1) {
            return null;
        }

        return hash_hkdf('sha256', (string) hex2bin($secret_hex), 32, 'glommer-staged-upload');
    }
}
