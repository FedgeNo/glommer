<?php

declare(strict_types=1);

abstract class Composer extends Form
{
    public ?string $class = 'Card d-flex flex-column gap-2 Composer';

    public function toDOM(): \DOMElement
    {
        $this -> action = ServerURL::absolute('/api/create-post');
        $this -> method = 'POST';
        $this -> enctype = 'multipart/form-data';

        $this -> addFields();

        $fields = new Fieldset($this -> legend());

        $title_link_row = new Div();
        $title_link_row -> class = 'PostComposerFields d-flex gap-2';
        $title_link_row -> addContent(new InputField('title', 'Title (optional)', 'text', 'Title (optional)', 255));
        $title_link_row -> addContent(new InputField('linkURL', 'Link (optional)', 'text', 'Link (optional)', 255));
        $fields -> addContent($title_link_row);

        // A class, not an id: this composer's editor coexists with an inline
        // post-edit form's own Quill instance elsewhere on the same page
        // (see main.js's create_quill_editor) - two elements can't share one
        // id, and only one page-wide Quill made that impossible before.
        $editor_container = new Div();
        $editor_container -> class = 'QuillEditor';
        $editor_container -> attributes['data-placeholder'] = $this -> editorPlaceholder();

        // Quill turns .QuillEditor itself into the toolbar's ql-container
        // sibling in place, so this inner wrapper (rather than .QuillEditor
        // directly) is what has to be the flex item that narrows - Quill's
        // toolbar ends up stacked above the editor inside it either way,
        // both narrowing together alongside the image.
        $editor_column = new Div();
        $editor_column -> class = 'EditorColumn';
        $editor_column -> addContent($editor_container);

        $editor_row = new Div();
        $editor_row -> class = 'EditorRow d-flex gap-2 align-items-start';
        $editor_row -> addContent(new LinkImagePreview());
        $editor_row -> addContent($editor_column);
        $fields -> addContent($editor_row);

        $desc_input = new HiddenInput();
        $desc_input -> name = 'description';
        $desc_input -> class = 'DescriptionInput';
        $fields -> addContent($desc_input);

        $this -> contents[] = $fields;

        $file_input = new FileInput();
        $file_input -> name = 'files[]';
        $file_input -> id = 'files';
        $file_input -> attributes['multiple'] = 'multiple';
        $file_input -> attributes['accept'] = 'image/*,video/*,audio/*';
        $file_input -> attributes['aria-label'] = 'Attach images, video, or audio';

        $submit_button = new SubmitButton($this -> submitLabel());
        $submit_button -> class = 'Btn';

        $actions = new Div();
        $actions -> class = 'd-flex align-items-center gap-2 ms-auto';
        $actions -> addContent(new RemoveFilesButton());
        $actions -> addContent($file_input);
        $actions -> addContent(new EmojiPickerButton());
        $actions -> addContent($submit_button);
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
