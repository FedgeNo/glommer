<?php

declare(strict_types=1);

/**
 * Leaflet plus its markercluster plugin, loaded from the CDN the same way Quill
 * is (no build step, plain global scripts). Only pulled in on the map page, via
 * Page::needsMap. Markercluster is what bundles points into count bubbles when
 * zoomed out and expands them to individual pins when zoomed in.
 */
class MapAssets
{
    private const LEAFLET_VERSION = '1.9.4';
    private const CLUSTER_VERSION = '1.5.3';

    /** @return Link[] */
    public static function CSSLinks(): array
    {
        return [
            self::css('https://cdn.jsdelivr.net/npm/leaflet@' . self::LEAFLET_VERSION . '/dist/leaflet.css'),
            self::css('https://cdn.jsdelivr.net/npm/leaflet.markercluster@' . self::CLUSTER_VERSION . '/dist/MarkerCluster.css'),
            self::css('https://cdn.jsdelivr.net/npm/leaflet.markercluster@' . self::CLUSTER_VERSION . '/dist/MarkerCluster.Default.css'),
        ];
    }

    /** @return Script[] */
    public static function JSScripts(): array
    {
        return [
            self::js('https://cdn.jsdelivr.net/npm/leaflet@' . self::LEAFLET_VERSION . '/dist/leaflet.js'),
            self::js('https://cdn.jsdelivr.net/npm/leaflet.markercluster@' . self::CLUSTER_VERSION . '/dist/leaflet.markercluster.js'),
        ];
    }

    private static function css(string $href): Link
    {
        $css = new Link();
        $css -> rel = 'stylesheet';
        $css -> href = $href;

        return $css;
    }

    private static function js(string $src): Script
    {
        $js = new Script();
        $js -> src = $src;

        return $js;
    }
}
