<?php

declare(strict_types=1);

/** Streams a known remote custom emoji without exposing the reader to its host. */
class RemoteEmoji
{
    private const MAX_BYTES = 2097152;
    private const SERVEABLE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'];

    public static function proxyURL(int $emoji_id): string
    {
        return ServerURL::absolute('/remote-emoji/' . $emoji_id);
    }

    public static function sourceFor(int $emoji_id): ?CustomEmojiData
    {
        return DB::row('
SELECT `customEmojiId`, `imageURL`
    FROM `CustomEmojis`
    WHERE `customEmojiId` = ?
', 'CustomEmojiData', 'i', $emoji_id);
    }

    public static function serve(int $emoji_id): void
    {
        $source = self::sourceFor($emoji_id);

        if ($source === null || !is_string($source -> imageURL)) {
            http_response_code(404);

            return;
        }

        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        $started = false;

        $delivered = SafeHTTPFetcher::stream(
            $source -> imageURL,
            ['Accept: ' . implode(', ', self::SERVEABLE_TYPES)],
            self::MAX_BYTES,
            static function (string $chunk, string $content_type) use (&$started): bool {
                if (!$started) {
                    $type = strtolower(trim(explode(';', $content_type)[0]));

                    if (!in_array($type, self::SERVEABLE_TYPES, true)) {
                        return false;
                    }

                    header('Content-Type: ' . $type);
                    header('X-Content-Type-Options: nosniff');
                    header('Content-Security-Policy: default-src \'none\'; sandbox');
                    header('Cache-Control: private, max-age=86400');
                    $started = true;
                }

                echo $chunk;
                flush();

                return true;
            }
        );

        if (!$delivered && !$started) {
            http_response_code(502);
        }
    }
}
