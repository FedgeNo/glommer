<?php

declare(strict_types=1);

/**
 * Serving the picture a remote account uses, without hotlinking it.
 *
 * The same reasoning as RemoteMedia, for the same reason: an <img> pointed at
 * the far server would hand it a log line - address, browser, referring page -
 * for every member who scrolls past a post by that account, which is a
 * member's reading habits leaked to a stranger. Copying it here instead would
 * make this server the host of whatever another chose to publish. So the bytes
 * pass through, fetched per request and never stored.
 *
 * What keeps this from being an open proxy is that the caller names an account,
 * not a URL. The address comes from Users.remoteActorIconURL, written when the
 * actor document was read and verified to belong to that actor's own server -
 * so the only things fetchable are pictures accounts this server already knows
 * have pointed at.
 */
class RemoteAvatar
{
    /** An avatar; anything claiming to be larger than this is not one. */
    private const MAX_BYTES = 2097152;

    /** @var string[] what a picture may answer as */
    private const SERVEABLE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'];

    public static function serve(int $user_id): void
    {
        $source = DB::row('
SELECT `remoteActorIconURL`
    FROM `Users`
    WHERE `userId` = ? AND `remoteActorURI` IS NOT NULL AND `banned` = ?
', 'stdClass', 'ii', $user_id, 0);

        if ($source === null || !is_string($source -> remoteActorIconURL)) {
            http_response_code(404);

            return;
        }

        // Buffering would undo the streaming - the response would be collected
        // in PHP anyway and sent in one piece at the end.
        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        $started = false;

        $delivered = SafeHTTPFetcher::stream(
            $source -> remoteActorIconURL,
            ['Accept: ' . implode(', ', self::SERVEABLE_TYPES)],
            self::MAX_BYTES,
            static function (string $chunk, string $content_type) use (&$started): bool {
                if (!$started) {
                    // Decided on the first chunk, before a single byte is
                    // written, so a refusal can still be a clean status code.
                    $type = strtolower(trim(explode(';', $content_type)[0]));

                    if (!in_array($type, self::SERVEABLE_TYPES, true)) {
                        return false;
                    }

                    header('Content-Type: ' . $type);
                    header('X-Content-Type-Options: nosniff');
                    header('Content-Security-Policy: default-src \'none\'; sandbox');
                    // Held briefly: an avatar changes rarely, and re-fetching
                    // one on every post in a feed would be a request per card.
                    header('Cache-Control: private, max-age=3600');

                    $started = true;
                }

                echo $chunk;
                flush();

                return true;
            }
        );

        if (!$delivered && !$started) {
            // Unreachable, too slow, refused, or answering with something that
            // is not a picture.
            http_response_code(502);
        }
    }
}
