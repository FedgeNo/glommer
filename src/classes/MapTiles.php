<?php

declare(strict_types=1);

/**
 * The map's tile source, configured in admin Site Settings. Defaults to
 * OpenStreetMap's standard tiles (keyless, works out of the box); an admin can
 * point it at a keyed provider (MapTiler, Stadia, ...) by pasting a URL template
 * and key. A literal {apiKey} in the template is replaced with the stored key.
 * The key ends up in the client's tile requests either way - map-tile keys are
 * meant to be referrer-restricted, not secret - so it's stored and shipped like
 * a public setting rather than a write-only one.
 */
class MapTiles
{
    public const URL_SETTING = 'mapTileURL';
    public const KEY_SETTING = 'mapTileAPIKey';
    public const ATTRIBUTION_SETTING = 'mapTileAttribution';

    private const DEFAULT_URL = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
    private const DEFAULT_ATTRIBUTION = '© OpenStreetMap contributors';

    /** The tile URL template with the stored API key substituted in, for Leaflet. */
    public static function url(): string
    {
        $template = (string) Settings::get(self::URL_SETTING, '');

        if ($template === '') {
            $template = self::DEFAULT_URL;
        }

        return str_replace('{apiKey}', (string) Settings::get(self::KEY_SETTING, ''), $template);
    }

    public static function attribution(): string
    {
        $attribution = (string) Settings::get(self::ATTRIBUTION_SETTING, '');

        return $attribution !== '' ? $attribution : self::DEFAULT_ATTRIBUTION;
    }
}
