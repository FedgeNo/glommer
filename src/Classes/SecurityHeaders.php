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

        $csp = implode('; ', [
            'default-src \'self\'',
            'script-src \'self\' \'nonce-' . $nonce . '\' https://cdn.jsdelivr.net',
            'style-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net',
            'img-src \'self\' data:',
            'font-src \'self\' https://cdn.jsdelivr.net',
            'media-src \'self\'',
            'connect-src \'self\' https://cdn.jsdelivr.net',
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
