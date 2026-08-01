<?php

declare(strict_types=1);

/**
 * Serving a picture, video or sound that lives on another server.
 *
 * Remote media is neither hotlinked nor copied here. Hotlinking would hand the
 * originating server a log line - IP address, user agent, referring page - for
 * every member who so much as scrolls past the post, which is a member's
 * reading habits leaked to a stranger. Copying it would make this server the
 * host of whatever another server chose to send, which is a moderation problem
 * and a disk-space one. So the bytes pass through: fetched per request,
 * streamed straight back out, never stored.
 *
 * What makes this a media proxy rather than an open one is that the caller
 * names an item, not a URL. The URL is looked up here from FeedItems, the same
 * lookup rendering the post does - so the only things fetchable are things an
 * accepted post already referenced. A URL supplied by a visitor is not a URL
 * this will ever request.
 */
class RemoteMedia
{
    /**
     * Big enough for a video a remote server thought worth publishing; small
     * enough that a hostile one cannot tie up the connection indefinitely.
     * Nothing is buffered, so this bounds transfer time, not memory.
     */
    private const MAX_BYTES = 41943040;

    private const PATH_PREFIX = '/media-';

    /**
     * What we will pass on, and as what. An allowlist rather than the
     * origin's word for it: this response is rendered in a member's browser,
     * so a server answering with text/html or image/svg+xml (both of which
     * carry script) has to come out as a refusal, not as a page on this site's
     * own origin.
     */
    private const SERVEABLE_TYPES = [
        'ImageItem' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'],
        'VideoItem' => ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime'],
        'AudioItem' => ['audio/mpeg', 'audio/mp4', 'audio/ogg', 'audio/aac', 'audio/flac', 'audio/wav', 'audio/x-wav'],
    ];

    /** What an inbound attachment's own mediaType makes it here. */
    public static function itemTypeFor(string $media_type): ?string
    {
        foreach (self::SERVEABLE_TYPES as $item_type => $types) {
            if (in_array(strtolower($media_type), $types, true)) {
                return $item_type;
            }
        }

        return null;
    }

    public static function proxyURL(int $item_id): string
    {
        return ServerURL::absolute(self::PATH_PREFIX . $item_id);
    }

    public static function sourceFor(int $item_id): ?FeedItemData
    {
        $row = DB::row('
SELECT `itemId`, `type`, `remoteURL`
    FROM `FeedItems`
    WHERE `itemId` = ?
', 'FeedItemData', 'i', $item_id);

        return ($row === null || $row -> remoteURL === null) ? null : $row;
    }

    /**
     * Streams one item's bytes to the client, or sends the appropriate refusal.
     * Writes the response itself rather than returning it, because the whole
     * point is not to hold the file in memory first.
     */
    public static function serve(int $item_id): void
    {
        $item = self::sourceFor($item_id);

        if ($item === null) {
            http_response_code(404);

            return;
        }

        $allowed = self::SERVEABLE_TYPES[(string) $item -> type] ?? null;

        if ($allowed === null) {
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
            (string) $item -> remoteURL,
            ['Accept: ' . implode(', ', $allowed)],
            self::MAX_BYTES,
            static function (string $chunk, string $content_type) use ($allowed, &$started): bool {
                if (!$started) {
                    // Decided on the first chunk, before a single byte is
                    // written, so a refusal can still be a clean status code.
                    $type = strtolower(trim(explode(';', $content_type)[0]));

                    if (!in_array($type, $allowed, true)) {
                        return false;
                    }

                    self::sendHeaders($type);
                    $started = true;
                }

                echo $chunk;
                flush();

                return true;
            }
        );

        if (!$delivered && !$started) {
            // Unreachable, too slow, refused, defederated since the post
            // arrived, or answering with something we won't render.
            http_response_code(502);
        }
    }

    private static function sendHeaders(string $content_type): void
    {
        header('Content-Type: ' . $content_type);

        // The file is another server's, and it is being served from this
        // site's own origin: nosniff stops the browser from deciding it is
        // really something executable, and the sandbox CSP means that even if
        // it did, there is no origin for it to act in.
        header('X-Content-Type-Options: nosniff');
        header('Content-Security-Policy: default-src \'none\'; sandbox');
        header('Content-Disposition: inline');

        // Private, because the proxy is only reachable while signed in and a
        // shared cache holding it would serve it to people who aren't.
        header('Cache-Control: private, max-age=86400');
    }
}
