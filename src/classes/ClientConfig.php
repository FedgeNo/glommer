<?php

declare(strict_types=1);

/**
 * Builds the client-side configuration and sends it as a JSON cookie, read back
 * by ClientConfig.js. Call ClientConfig::send() before the response body is sent
 * (Page::assembleBody() does). A value only one page needs is passed as an
 * override rather than added here; a value only one page's script needs and that
 * shouldn't ride on every request belongs in a JSGlobals block instead.
 */
class ClientConfig
{
    /** @param array<string, mixed> $overrides Additional or override values */
    public static function send(array $overrides = []): void
    {
        $current_user = Auth::user();

        $config = array_merge([
            'currentUserId' => $current_user ?-> userId,
            'currentUserUsername' => $current_user ?-> slug,
            'currentUserSkinTone' => $current_user ?-> skinTone,
            'currentUserCanModerate' => Auth::canModerate(),
            'siteURL' => ServerURL::absolute(''),
            'serverTime' => time() * 1000,
            'WSPort' => Config::get('WSPort'),
            'carouselEagerItems' => Carousel::INITIAL_EAGER_ITEMS,
            // The composer is built in JavaScript, so the durations a poll may
            // run for are shipped rather than restated there - two lists of the
            // same thing would eventually disagree, and the server refuses any
            // duration not in its own.
            'pollDurations' => (object) Poll::DURATIONS,
            'pollMaxOptions' => Poll::MAX_OPTIONS,
            'needsMath' => false,
        ], $overrides);

        $json = json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        setcookie(
            'APP-CONFIG',
            $json,
            [
                'expires'  => 0,               // session cookie
                'path'     => '/',
                'secure'   => true,
                'httponly' => false,
                'samesite' => 'Strict'
            ]
        );
    }
}
