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
            // 'unsafe-inline' is here for emoji-picker-element, which builds its
            // own <style> element and sets textContent on it inside its shadow
            // root (picker.js: attachShadow, then createElement('style')). CSP
            // reaches into shadow DOM, so without this the emoji picker renders
            // completely unstyled. It can't be narrowed to a nonce either -
            // third-party library code has no way to carry ours.
            //
            // The site's own markup needs none of it: no page renders a <style>
            // block, and the handful of inline style attributes it does emit
            // (AvatarInitial's hue, two display:none) could become classes. JS
            // setting el.style.* is CSSOM and isn't governed by this at all, so
            // Leaflet's tile positioning doesn't depend on it.
            'style-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net https://fonts.googleapis.com',
            // This origin only. Every picture from anywhere else is fetched by
            // the server and served from here - remote media through
            // /media-N, a remote account's avatar through /remote-avatar/N -
            // so nothing on a page needs to reach another host for an image,
            // and a scheme-wide https: would have let a post carry a beacon
            // that reports who read it and from where. data: serves inline
            // placeholders; blob: serves attachment previews still in the
            // browser's memory.
            'img-src \'self\' data: blob:',
            'font-src \'self\' https://cdn.jsdelivr.net https://fonts.gstatic.com',
            'media-src \'self\'',
            'frame-src https://challenges.cloudflare.com https://www.google.com',
            // The socket is this host's own, on its own port - the client builds
            // that address from window.location.hostname and can reach nowhere
            // else. Naming the host rather than wildcarding it keeps connect-src
            // from being a way to talk to somebody else's server on that port.
            'connect-src \'self\' https://cdn.jsdelivr.net https://challenges.cloudflare.com https://www.google.com https://www.gstatic.com wss://' . ServerURL::host() . ':' . Config::get('WSPort'),
            'object-src \'none\'',
            'base-uri \'self\'',
            'form-action \'self\'',
            'frame-ancestors \'none\'',
            // report-uri is nominally deprecated but is the one reporting
            // mechanism every browser speaks without the Reporting-Endpoints
            // header machinery. Violations land in the CSPReports table.
            'report-uri /api/csp-report',
        ]);

        header('Content-Security-Policy: ' . $csp);
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');

        if ($is_https) {
            // preload commits the domain to the browsers' baked-in HSTS lists
            // permanently: nothing on it or any subdomain can ever be served
            // over plain HTTP again. A deliberate operator decision.
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
    }
}
