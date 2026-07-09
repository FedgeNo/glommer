<?php

declare(strict_types=1);

abstract class Composer extends Form
{
    public ?string $class = 'Card d-flex flex-column gap-2 Composer';

    public function toDOM(): \DOMElement
    {
        $this -> action = URL::absolute('/api/create-post');
        $this -> method = 'POST';
        $this -> enctype = 'multipart/form-data';

        $this -> addFields();

        $fields = new Fieldset($this -> legend());

        $title_link_row = new Div();
        $title_link_row -> class = 'PostComposerFields d-flex gap-2';
        $title_link_row -> addContents(new InputField('title', 'Title (optional)', 'text', 'Title (optional)', 255));
        $title_link_row -> addContents(new InputField('linkURL', 'Link (optional)', 'text', 'Link (optional)', 255));
        $fields -> addContents($title_link_row);

        $editor_container = new Div();
        $editor_container -> id = 'editor';
        $editor_container -> attributes['data-placeholder'] = $this -> editorPlaceholder();

        // Quill turns #editor itself into the toolbar's ql-container sibling
        // in place, so this inner wrapper (rather than #editor directly) is
        // what has to be the flex item that narrows - Quill's toolbar ends
        // up stacked above the editor inside it either way, both narrowing
        // together alongside the image.
        $editor_column = new Div();
        $editor_column -> class = 'EditorColumn';
        $editor_column -> addContents($editor_container);

        $editor_row = new Div();
        $editor_row -> class = 'EditorRow d-flex gap-2 align-items-start';
        $editor_row -> addContents(new LinkImagePreview());
        $editor_row -> addContents($editor_column);
        $fields -> addContents($editor_row);

        $desc_input = new HiddenInput();
        $desc_input -> name = 'description';
        $desc_input -> id = 'description-input';
        $fields -> addContents($desc_input);

        $this -> contents[] = $fields;

        $file_input = new FileInput();
        $file_input -> name = 'files[]';
        $file_input -> id = 'files';
        $file_input -> attributes['multiple'] = 'multiple';
        $file_input -> attributes['accept'] = 'image/*,video/*,audio/*';
        $file_input -> attributes['aria-label'] = 'Attach images, video, or audio';

        $cancel_file_button = new Button();
        $cancel_file_button -> type = 'button';
        $cancel_file_button -> class = 'Btn CancelFileButton';
        $cancel_file_button -> attributes['style'] = 'display: none';
        $cancel_file_button -> contents[] = 'Cancel';

        $submit_button = new Button();
        $submit_button -> type = 'submit';
        $submit_button -> class = 'Btn';
        $submit_button -> contents[] = $this -> submitLabel();

        $actions = new Div();
        $actions -> class = 'd-flex align-items-center gap-2 ms-auto';
        $actions -> addContents($cancel_file_button);
        $actions -> addContents($file_input);
        $actions -> addContents(new EmojiPickerButton());
        $actions -> addContents($submit_button);
        $this -> contents[] = $actions;

        $progress_bar = new ProgressBar();
        $this -> contents[] = $progress_bar;

        return parent::toDOM();
    }

    /** Add any fields specific to this composer variant, ahead of the shared editor/file/submit controls. */
    protected function addFields(): void
    {
    }

    abstract protected function legend(): string;

    abstract protected function editorPlaceholder(): string;

    abstract protected function submitLabel(): string;
}
