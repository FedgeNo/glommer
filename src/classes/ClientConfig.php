<?php

declare(strict_types=1);

/**
 * Builds the client-side configuration, which Page renders into the document as
 * a JSON block for ClientConfig.js to read. This is the only channel
 * server-side values reach the client by: a value every page wants is listed
 * here, and a value one page needs rides through Page::$clientConfig as an
 * override.
 *
 * In the page rather than in a cookie, for two reasons. A cookie is sent back
 * up on every subsequent request, which is pure weight for values the server
 * already knows; and a cookie is shared by every tab, so config belonging to
 * one page render was reachable from another page's tab.
 */
class ClientConfig
{
    /**
     * @param array<string, mixed> $overrides Additional or override values
     * @return array<string, mixed>
     */
    public static function payload(array $overrides = []): array
    {
        $current_user = Auth::user();

        return array_merge([
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
            'needsMath' => false,
        ], $overrides);
    }
}
