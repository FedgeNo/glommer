<?php

declare(strict_types=1);

class SecurityHeaders
{
    private static ?string $nonce = null;

    public static function nonce(): string
    {
        if (self::$nonce === null) {
            self::$nonce = base64_encode(random_bytes(16));
        }

        return self::$nonce;
    }

    public static function send(): void
    {
        $is_https = ($_SERVER['HTTPS'] ?? '') !== '';
        $nonce = self::nonce();
        $config = require __DIR__ . '/../config.php';

        $csp = implode('; ', [
            'default-src \'self\'',
            'script-src \'self\' \'nonce-' . $nonce . '\' https://cdn.jsdelivr.net',
            'style-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net',
            'img-src \'self\' data:',
            'font-src \'self\' https://cdn.jsdelivr.net',
            'media-src \'self\'',
            // The WebSocket connection is a different origin from the page
            // (same host, different port) - 'self' doesn't cover it, and the
            // actual hostname varies with however the site is reached, so
            // this allows the configured port on any host rather than one
            // hardcoded hostname.
            'connect-src \'self\' https://cdn.jsdelivr.net ws://*:' . $config['wsPort'] . ' wss://*:' . $config['wsPort'],
            'object-src \'none\'',
            'base-uri \'self\'',
            'form-action \'self\'',
            'frame-ancestors \'none\'',
        ]);

        header('Content-Security-Policy: ' . $csp);
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');

        if ($is_https) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
}
