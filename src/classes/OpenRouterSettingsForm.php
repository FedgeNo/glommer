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
        $fields = new Fieldset('OpenRouter');
        $fields -> addContent(new Notice('Used by AI features on the site (trending-topic summaries, etc). Leave the model blank to use the Free Models Router, which OpenRouter picks at random from whatever is currently free and can never incur cost.'));

        $key_is_set = (string) Settings::get(OpenRouter::API_KEY_SETTING, '') !== '';
        $key_placeholder = $key_is_set
            ? 'API key is set - leave blank to keep it'
            : 'OpenRouter API key';
        $key = new InputField('openRouterAPIKey', 'API key', 'text', $key_placeholder, 255);
        $key -> autocomplete = 'off';
        $key -> labelVisible = true;
        $fields -> addContent($key);

        // A blank submit keeps the stored key, so clearing it needs its own
        // deliberate control - this is the only way the form can turn AI
        // features off. Only offered while there's a key to remove.
        if ($key_is_set) {
            $fields -> addContent(new CheckboxField('clearOpenRouterAPIKey', 'Remove the stored API key (turns AI features off)'));
        }

        $model = new InputField('openRouterModel', 'Model', 'text', OpenRouter::DEFAULT_MODEL, 255);
        $model -> value = (string) Settings::get(OpenRouter::MODEL_SETTING, '');
        $model -> autocomplete = 'off';
        $model -> labelVisible = true;
        $fields -> addContent($model);

        $never_spend = new CheckboxField('openRouterNeverSpend', 'Never allow this to spend money (recommended)');
        $never_spend -> checked = OpenRouter::neverSpend();
        $fields -> addContent($never_spend);

        $this -> contents[] = $fields;

        $this -> contents[] = new Paragraph('With the guard on, every request caps its price at zero, so it fails outright rather than falling back to a paid model when no free provider is available. Remove the stored API key to turn AI features off entirely.');

        $this -> contents[] = new SubmitButton('Save');

        return parent::toDOM();
    }
}
