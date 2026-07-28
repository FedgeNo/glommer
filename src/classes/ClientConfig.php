<?php

declare(strict_types=1);

/**
 * Builds the client-side configuration and sends it as a JSON cookie.
 *
 * Replaces the old JSGlobals inline script. Call ClientConfig::send() before
 * the response body is sent (typically in Page::assembleBody()).
 */
class ClientConfig
{
    /** @param array<string, mixed> $overrides Additional or override values */
    public static function send(array $overrides = []): void
    {
        $current_user = Auth::user();

        $config = [
            'currentUserId'         => $current_user?->userId,
            'currentUserUsername'   => $current_user?->slug,
            'currentUserSkinTone'   => $current_user?->skinTone,
            'currentUserCanModerate' => Auth::canModerate(),
            'siteURL'               => ServerURL::absolute(''),
            'serverTime'            => time() * 1000,
            'WSPort'                => Config::get('WSPort'),
            'carouselEagerItems'    => Carousel::INITIAL_EAGER_ITEMS,
            'needsMath'             => false,   // overridden per-page if needed
        ];

        // Merge page‑specific values (like needsMath)
        $config = array_merge($config, $overrides);

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
