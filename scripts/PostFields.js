/**
 * The fields a post is written with, shared by the composer and the post
 * editor. One definition of each control, so the two ways of writing a post
 * cannot drift apart in names, caps, or what assistive tech is told.
 *
 * Every input is named by a real label, visually hidden: read out, never laid
 * out. The label stands immediately before its input under the same parent,
 * tied to it by a generated id, so nothing about the visible layout moves.
 */
export class PostFields {
    static #labelId = 0;

    /**
     * A visually hidden label tied to the input - the name assistive tech
     * reads, invisible to the layout (the class positions it out of flow).
     */
    static fieldLabel(input, text) {
        input.id = 'PostField' + ++PostFields.#labelId;

        const label = document.createElement('label');
        label.className = 'visually-hidden';
        label.htmlFor = input.id;
        label.textContent = text;

        return label;
    }

    /** The title box. @returns {[HTMLLabelElement, HTMLInputElement]} label first, then input */
    static titleField(value = '') {
        const input = document.createElement('input');
        input.type = 'text';
        input.name = 'title';
        input.placeholder = 'Title (optional)';
        input.maxLength = 255;
        input.value = value;

        return [PostFields.fieldLabel(input, 'Title (optional)'), input];
    }

    /** The link box. @returns {[HTMLLabelElement, HTMLInputElement]} */
    static linkField(value = '') {
        const input = document.createElement('input');
        input.type = 'text';
        input.name = 'linkURL';
        input.placeholder = 'Link (optional)';
        input.maxLength = 255;
        input.value = value;

        return [PostFields.fieldLabel(input, 'Link (optional)'), input];
    }

    /**
     * The alt-text box an attached image row carries. The label names the
     * image it describes where the caller knows a name for it.
     *
     * @returns {[HTMLLabelElement, HTMLInputElement]}
     */
    static altTextField(labelText, value = '') {
        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'ComposerAttachmentAltInput';
        input.placeholder = 'Alt text - describe this image';
        input.maxLength = 1000;
        input.value = value;

        return [PostFields.fieldLabel(input, labelText), input];
    }

    /** The content-warning box. @returns {[HTMLLabelElement, HTMLInputElement]} */
    static contentWarningField(value = '') {
        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'ContentWarningInput';
        input.name = 'contentWarning';
        input.placeholder = 'Content Warning (optional)';
        input.maxLength = 255;
        input.value = value;

        return [PostFields.fieldLabel(input, 'Content Warning (optional)'), input];
    }
}
