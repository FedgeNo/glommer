<?php

declare(strict_types=1);

/**
 * Builds the client-side configuration and sends it as a JSON cookie, read back
 * by ClientConfig.js. Call ClientConfig::send() before the response body is sent
 * (Page::send() does). This is the only channel server-side values reach the
 * client by: a value every page wants is listed here, and a value one page
 * needs rides through Page::$clientConfig as an override.
 *
 * Configuration only - what the site is and who is signed in. The data of
 * whatever is being looked at (a conversation's keys, a list's next page)
 * belongs on the element that needs it or in the endpoint that serves it: the
 * cookie is sent back up on every request from then on, so anything put here
 * is paid for by every image, script and API call the page makes afterwards.
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
            'showSensitiveMedia' => SensitiveMedia::shownByDefault(),
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
            // The applicationServerKey a browser subscribes to push with;
            // null when this server has no VAPID keypair, which is how the
            // client knows push isn't on offer here.
            'vapidPublicKey' => WebPushKeys::publicKeyForBrowser(),
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
