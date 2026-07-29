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
        $is_https = ServerURL::isHTTPS();
        $nonce = self::nonce();

        $csp = implode('; ', [
            'default-src \'self\'',
            'script-src \'self\' \'nonce-' . $nonce . '\' https://cdn.jsdelivr.net https://challenges.cloudflare.com https://www.google.com https://www.gstatic.com',
            'style-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net https://fonts.googleapis.com',
            // Map tiles come from a configurable (admin-set) provider host, and
            // Leaflet's marker icons from the jsDelivr CDN - both are <img> loads
            // from hosts not known here (this runs before the DB is up, so the
            // configured tile host can't be read). Tiles are non-executable
            // images, so allowing any HTTPS image source is a contained widening.
            'img-src \'self\' data: https:',
            'font-src \'self\' https://cdn.jsdelivr.net https://fonts.gstatic.com',
            'media-src \'self\'',
            'frame-src https://challenges.cloudflare.com https://www.google.com',
            'connect-src \'self\' https://cdn.jsdelivr.net https://challenges.cloudflare.com https://www.google.com https://www.gstatic.com wss://*:' . Config::get('WSPort'),
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
