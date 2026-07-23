<?php

declare(strict_types=1);

/**
 * The admin Site Settings form for one editable site-info text (the About
 * overview, Terms of Service, or Privacy Policy): a textarea prefilled with
 * the current text (shipped default included, so the admin edits from
 * something rather than a blank box) and a save button. One descendant per
 * info text.
 */
abstract class InfoSettingsForm extends Form
{
    public ?string $class = 'Card d-flex flex-column gap-2 InfoSettingsForm';
    public ?string $description = 'Plain text - blank lines separate paragraphs.';

    /** The POST field / Settings name this form edits. */
    protected string $settingName = '';
    protected string $legend = '';

    abstract protected function currentText(): string;

    public function toDOM(): \DOMElement
    {
        $this -> action = ServerURL::absolute('/admin/settings');
        $this -> method = 'POST';

        $fields = new Fieldset($this -> legend);

        $textarea = new TextareaField($this -> settingName, $this -> legend, null, 65535);
        $textarea -> value = $this -> currentText();
        $fields -> addContent($textarea);

        $this -> contents[] = $fields;

        $this -> contents[] = new Notice($this -> description);

        $this -> contents[] = new SubmitButton('Save');

        return parent::toDOM();
    }
}
