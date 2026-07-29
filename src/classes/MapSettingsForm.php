<?php

declare(strict_types=1);

/**
 * The admin Site Settings map form: the tile source Leaflet uses on /map. A URL
 * template (with a literal {apiKey} where the provider wants the key), the API
 * key itself, and the attribution text. All fall back to keyless OpenStreetMap
 * when left blank, so the map works with nothing configured.
 */
class MapSettingsForm extends Form
{
    public ?string $class = 'MapSettingsForm';
    public array $mixins = ['Card', 'd-flex', 'flex-column', 'gap-2'];

    public function toDOM(): \DOMElement
    {
        $fields = new Fieldset('Map tiles');
        $fields -> addContent(new Notice('Leave blank to use OpenStreetMap. To use a keyed provider, paste its URL template with a literal {apiKey} where the key goes, plus the key and attribution below.'));

        $url = new InputField('mapTileURL', 'Tile URL template', 'text', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', 255);
        $url -> value = (string) Settings::get(MapTiles::URL_SETTING, '');
        $url -> autocomplete = 'off';
        $url -> labelVisible = true;
        $fields -> addContent($url);

        $key = new InputField('mapTileAPIKey', 'API key', 'text', 'Your tile provider API key', 255);
        $key -> value = (string) Settings::get(MapTiles::KEY_SETTING, '');
        $key -> autocomplete = 'off';
        $key -> labelVisible = true;
        $fields -> addContent($key);

        $attribution = new InputField('mapTileAttribution', 'Attribution', 'text', '© OpenStreetMap contributors', 255);
        $attribution -> value = (string) Settings::get(MapTiles::ATTRIBUTION_SETTING, '');
        $attribution -> autocomplete = 'off';
        $attribution -> labelVisible = true;
        $fields -> addContent($attribution);

        $this -> contents[] = $fields;
        $this -> contents[] = new SubmitButton('Save');

        return parent::toDOM();
    }
}
