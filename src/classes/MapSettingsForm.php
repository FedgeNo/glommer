<?php

declare(strict_types=1);

/**
 * The Admin Settings map form: the tile source Leaflet uses on /map. A URL
 * template (with a literal {apiKey} where the provider wants the key), the API
 * key itself, and the attribution text. All fall back to keyless OpenStreetMap
 * when left blank, so the map works with nothing configured.
 */
class MapSettingsForm extends FormForm
{
    public array $mixins = ['d-flex', 'flex-column', 'gap-2'];

    public function toDOM(): \DOMElement
    {
        $words = Strings::for(self::class);

        $fields = new Fieldset((string) ($words['legend'] ?? ''));
        $fields -> addContent(new Notice((string) ($words['notice'] ?? '')));

        // The template itself is syntax to paste ({z}/{x}/{y}, an {apiKey}
        // token), not prose, so unlike the labels around it, it stays here
        // rather than moving to the locale.
        $url = new InputField('mapTileURL', (string) ($words['urlLabel'] ?? ''), 'text', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', 255);
        $url -> value = (string) Settings::get(MapTiles::URL_SETTING, '');
        $url -> autocomplete = 'off';
        $url -> labelVisible = true;
        $fields -> addContent($url);

        $key = new InputField('mapTileAPIKey', (string) ($words['keyLabel'] ?? ''), 'text', (string) ($words['keyPlaceholder'] ?? ''), 255);
        $key -> value = (string) Settings::get(MapTiles::KEY_SETTING, '');
        $key -> autocomplete = 'off';
        $key -> labelVisible = true;
        $fields -> addContent($key);

        $attribution = new InputField('mapTileAttribution', (string) ($words['attributionLabel'] ?? ''), 'text', (string) ($words['attributionPlaceholder'] ?? ''), 255);
        $attribution -> value = (string) Settings::get(MapTiles::ATTRIBUTION_SETTING, '');
        $attribution -> autocomplete = 'off';
        $attribution -> labelVisible = true;
        $fields -> addContent($attribution);

        $this -> contents[] = $fields;
        $this -> contents[] = new SubmitButton((string) ($words['save'] ?? ''));

        return parent::toDOM();
    }
}
