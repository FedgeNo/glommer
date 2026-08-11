<?php

declare(strict_types=1);

/**
 * The admin Site Settings form for OpenRouter, the model provider AI features
 * on the site use (trending-topic summaries, etc.). The API key is write-only,
 * never rendered back, and a blank submit leaves the stored key unchanged - the
 * same treatment as the Turnstile and Google Auth keys.
 *
 * The model defaults to the Free Models Router (openrouter/free) - genuinely
 * free, not just cheap - and the spend guard defaults on, so a feature built on
 * this starts as free as OpenRouter allows until an admin deliberately opens it
 * up.
 */
class OpenRouterSettingsForm extends FormForm
{
    public array $mixins = ['d-flex', 'flex-column', 'gap-2'];

    public function toDOM(): \DOMElement
    {
        $words = Strings::for(self::class);

        $fields = new Fieldset((string) ($words['legend'] ?? ''));
        $fields -> addContent(new Notice((string) ($words['notice'] ?? '')));

        $key_is_set = (string) Settings::get(OpenRouter::API_KEY_SETTING, '') !== '';
        $key_placeholder = (string) ($key_is_set
            ? ($words['keyPlaceholder']['set'] ?? '')
            : ($words['keyPlaceholder']['unset'] ?? ''));
        $key = new InputField('openRouterAPIKey', (string) ($words['keyLabel'] ?? ''), 'text', $key_placeholder, 255);
        $key -> autocomplete = 'off';
        $key -> labelVisible = true;
        $fields -> addContent($key);

        // A blank submit keeps the stored key, so clearing it needs its own
        // deliberate control - this is the only way the form can turn AI
        // features off. Only offered while there's a key to remove.
        if ($key_is_set) {
            $fields -> addContent(new CheckboxField('clearOpenRouterAPIKey', (string) ($words['clearKeyLabel'] ?? '')));
        }

        // DEFAULT_MODEL is an OpenRouter model id (e.g. "openrouter/free"), not
        // English prose, so it stays here rather than moving to the locale.
        $model = new InputField('openRouterModel', (string) ($words['modelLabel'] ?? ''), 'text', OpenRouter::DEFAULT_MODEL, 255);
        $model -> value = (string) Settings::get(OpenRouter::MODEL_SETTING, '');
        $model -> autocomplete = 'off';
        $model -> labelVisible = true;
        $fields -> addContent($model);

        $never_spend = new CheckboxField('openRouterNeverSpend', (string) ($words['neverSpendLabel'] ?? ''));
        $never_spend -> checked = OpenRouter::neverSpend();
        $fields -> addContent($never_spend);

        $this -> contents[] = $fields;

        $this -> contents[] = new Paragraph((string) ($words['explainer'] ?? ''));

        $this -> contents[] = new SubmitButton((string) ($words['save'] ?? ''));

        return parent::toDOM();
    }
}
