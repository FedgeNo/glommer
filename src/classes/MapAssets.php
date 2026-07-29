<?php

declare(strict_types=1);

/**
 * Leaflet plus its markercluster plugin, loaded from the CDN the same way Quill
 * is (no build step, plain global scripts). Only pulled in on the map page, via
 * Page::needsMap. Markercluster is what bundles points into count bubbles when
 * zoomed out and expands them to individual pins when zoomed in, and it attaches
 * to the global L, so Leaflet has to load first - hence the fixed order below.
 *
 * Each file is pinned to its exact bytes with an sha384 integrity hash (computed
 * from what jsdelivr actually serves for these versions), so a compromised or
 * MITM'd CDN can't swap different code in under a trusted URL. Bump a hash
 * alongside its version if either is ever upgraded - a version bump with a stale
 * hash breaks the map outright (the browser blocks the mismatched file) rather
 * than silently reintroducing the gap.
 */
class MapAssets
{
    private const LEAFLET_VERSION = '1.9.4';
    private const CLUSTER_VERSION = '1.5.3';

    private const LEAFLET_CSS_INTEGRITY = 'sha384-sHL9NAb7lN7rfvG5lfHpm643Xkcjzp4jFvuavGOndn6pjVqS6ny56CAt3nsEVT4H';
    private const LEAFLET_JS_INTEGRITY = 'sha384-cxOPjt7s7Iz04uaHJceBmS+qpjv2JkIHNVcuOrM+YHwZOmJGBXI00mdUXEq65HTH';
    private const CLUSTER_CSS_INTEGRITY = 'sha384-pmjIAcz2bAn0xukfxADbZIb3t8oRT9Sv0rvO+BR5Csr6Dhqq+nZs59P0pPKQJkEV';
    private const CLUSTER_DEFAULT_CSS_INTEGRITY = 'sha384-wgw+aLYNQ7dlhK47ZPK7FRACiq7ROZwgFNg0m04avm4CaXS+Z9Y7nMu8yNjBKYC+';
    private const CLUSTER_JS_INTEGRITY = 'sha384-eXVCORTRlv4FUUgS/xmOyr66XBVraen8ATNLMESp92FKXLAMiKkerixTiBvXriZr';

    /** @return Link[] */
    public static function CSSLinks(): array
    {
        return [
            self::css(
                'https://cdn.jsdelivr.net/npm/leaflet@' . self::LEAFLET_VERSION . '/dist/leaflet.css',
                self::LEAFLET_CSS_INTEGRITY
            ),
            self::css(
                'https://cdn.jsdelivr.net/npm/leaflet.markercluster@' . self::CLUSTER_VERSION . '/dist/MarkerCluster.css',
                self::CLUSTER_CSS_INTEGRITY
            ),
            self::css(
                'https://cdn.jsdelivr.net/npm/leaflet.markercluster@' . self::CLUSTER_VERSION . '/dist/MarkerCluster.Default.css',
                self::CLUSTER_DEFAULT_CSS_INTEGRITY
            ),
        ];
    }

    /** @return Script[] */
    public static function JSScripts(): array
    {
        return [
            self::js(
                'https://cdn.jsdelivr.net/npm/leaflet@' . self::LEAFLET_VERSION . '/dist/leaflet.js',
                self::LEAFLET_JS_INTEGRITY
            ),
            self::js(
                'https://cdn.jsdelivr.net/npm/leaflet.markercluster@' . self::CLUSTER_VERSION . '/dist/leaflet.markercluster.js',
                self::CLUSTER_JS_INTEGRITY
            ),
        ];
    }

    private static function css(string $href, string $integrity): Link
    {
        $css = new Link();
        $css -> rel = 'stylesheet';
        $css -> href = $href;
        $css -> attributes['integrity'] = $integrity;
        $css -> attributes['crossorigin'] = 'anonymous';

        return $css;
    }

    private static function js(string $src, string $integrity): Script
    {
        $js = new Script();
        $js -> src = $src;
        $js -> attributes['integrity'] = $integrity;
        $js -> attributes['crossorigin'] = 'anonymous';

        return $js;
    }
}
