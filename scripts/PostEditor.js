import { ClientConfig } from '/scripts/ClientConfig.js';
import { Toast } from '/scripts/Toast.js';
import { Post } from '/scripts/Post.js';
import { QuillEditor } from '/scripts/QuillEditor.js';
import { render_math } from '/scripts/MathRenderer.js';
import { Api } from '/scripts/Api.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Working } from '/scripts/Working.js';

export class PostEditor {
    #postElement;
    #postData;
    #form;
    #quillEditor;

    /** Which alt-text field belongs to which image row, for the save below. */
    #altInputs = [];

    /**
     * Bind the delegated click for edit buttons.
     */
    static init() {
        document.addEventListener('click', (event) => {
            const button = event.target.closest('.PostEditButton');
            if (button) {
                PostEditor.open(button);
            }
        });
    }

    /**
     * Open the editor for the post associated with the given button.
     */
    static open(button) {
        const postElement = button.closest('.Post');
        const postData = postElement ? postElement.dataset : null;

        if (!postData || postData.descriptionDelta === undefined) {
            return;
        }

        if (postElement.nextElementSibling?.classList.contains('PostEditForm')) {
            return;
        }

        const editor = new PostEditor(postElement, postData);
        editor.#createForm();
    }

    constructor(postElement, postData) {
        this.#postElement = postElement;
        this.#postData = postData;
        this.#form = null;
        this.#quillEditor = null;
    }

    #createForm() {
        const post = this.#postElement;
        const data = this.#postData;

        post.style.display = 'none';

        const form = document.createElement('form');
        form.className = 'Form PostEditForm d-flex flex-column gap-2';

        const fields = document.createElement('fieldset');

        const titleRow = document.createElement('div');
        titleRow.className = 'PostComposerFields d-flex gap-2';

        const titleInput = document.createElement('input');
        titleInput.type = 'text';
        titleInput.name = 'title';
        titleInput.placeholder = 'Title (optional)';
        titleInput.maxLength = 255;
        titleInput.value = data.title || '';
        titleInput.setAttribute('aria-label', 'Title (optional)');
        titleRow.appendWithSpace(titleInput);

        if (!data.hasMedia) {
            const linkInput = document.createElement('input');
            linkInput.type = 'text';
            linkInput.name = 'linkURL';
            linkInput.placeholder = 'Link (optional)';
            linkInput.maxLength = 255;
            linkInput.value = data.linkUrl || '';
            linkInput.setAttribute('aria-label', 'Link (optional)');
            titleRow.appendWithSpace(linkInput);
        }

        fields.appendWithSpace(titleRow);

        const editorColumn = document.createElement('div');
        editorColumn.className = 'EditorColumn';

        const editorContainer = document.createElement('div');
        editorContainer.className = 'QuillEditor';
        editorColumn.appendWithSpace(editorContainer);

        fields.appendWithSpace(editorColumn);

        const descriptionInput = document.createElement('input');
        descriptionInput.type = 'hidden';
        descriptionInput.className = 'DescriptionInput';
        descriptionInput.name = 'description';
        fields.appendWithSpace(descriptionInput);

        // One alt-text field per attached image, prefilled from the rendered
        // item's raw value (data-alt-text - never the img's alt, whose "Image"
        // fallback would read back as the author's own words). The images
        // themselves are fixed at creation; how they're described is not.
        const imageItems = post.querySelectorAll('.ImageItem[data-item-id]');

        if (imageItems.length > 0) {
            const altList = document.createElement('ul');
            altList.className = 'PostEditAltList d-flex flex-column gap-2';

            for (const item of imageItems) {
                const row = document.createElement('li');
                row.className = 'PostEditAltRow d-flex align-items-center gap-2';

                const sourceImage = item.querySelector('img');
                const thumb = document.createElement('img');
                thumb.className = 'ComposerAttachmentThumb';
                thumb.src = sourceImage?.currentSrc || sourceImage?.src || sourceImage?.dataset.src || '';
                thumb.alt = '';
                row.appendWithSpace(thumb);

                const altInput = document.createElement('input');
                altInput.type = 'text';
                altInput.className = 'ComposerAttachmentAltInput';
                altInput.placeholder = 'Alt text - describe this image';
                altInput.maxLength = 1000;
                altInput.value = item.dataset.altText || '';
                altInput.setAttribute('aria-label', 'Alt text');
                row.appendWithSpace(altInput);

                this.#altInputs.push({ itemId: item.dataset.itemId, input: altInput });

                altList.appendWithSpace(row);
            }

            fields.appendWithSpace(altList);
        }

        form.appendWithSpace(fields);

        // Under what is being written, the same place the composer puts it.
        const warningInput = document.createElement('input');
        warningInput.type = 'text';
        warningInput.className = 'ContentWarningInput';
        warningInput.name = 'contentWarning';
        warningInput.maxLength = 255;
        warningInput.placeholder = 'Content Warning (optional)';
        warningInput.setAttribute('aria-label', 'Content warning (optional)');
        warningInput.value = data.contentWarning || '';
        warningInput.style.display = data.sensitive === '1' ? '' : 'none';
        form.appendWithSpace(warningInput);

        const actions = document.createElement('div');
        actions.className = 'd-flex align-items-center gap-2 ms-auto';

        // How the post is classified can be revised even though the media
        // cannot - opened with whatever it already carries, so saving an
        // unrelated typo fix doesn't quietly unmark it. Offered on every post,
        // media or not: the warning gates the words too.
        const sensitiveToggle = document.createElement('label');
        sensitiveToggle.className = 'SensitiveMediaToggle';

        const sensitiveInput = document.createElement('input');
        sensitiveInput.type = 'checkbox';
        sensitiveInput.name = 'sensitive';
        sensitiveInput.checked = data.sensitive === '1';
        sensitiveToggle.appendWithSpace(sensitiveInput);
        sensitiveToggle.appendWithSpace(document.createTextNode('Sensitive'));

        actions.appendWithSpace(sensitiveToggle);

        sensitiveInput.addEventListener('change', () => {
            warningInput.style.display = sensitiveInput.checked ? '' : 'none';

            if (!sensitiveInput.checked) {
                warningInput.value = '';
            }
        });

        const cancelButton = document.createElement('button');
        cancelButton.type = 'button';
        cancelButton.className = 'Button EditFormCancelButton';
        cancelButton.textContent = 'Cancel';
        cancelButton.addEventListener('click', () => this.#cancel());
        actions.appendWithSpace(cancelButton);

        const saveButton = document.createElement('button');
        saveButton.type = 'submit';
        saveButton.className = 'Button';
        saveButton.textContent = 'Save';
        actions.appendWithSpace(saveButton);

        form.appendWithSpace(actions);

        post.insertAdjacentElement('afterend', form);

        const editor = new QuillEditor(editorContainer, { placeholder: 'Edit your post…' });
        this.#quillEditor = editor;

        try {
            const delta = data.descriptionDelta
                ? JSON.parse(data.descriptionDelta)
                : { ops: [] };
            editor.instance.setContents(delta);
        } catch (_) {}

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            this.#save();
        });

        this.#form = form;
    }

    #cancel() {
        this.#postElement.style.display = '';
        this.#form.remove();
        this.#form = null;
        this.#quillEditor = null;
    }

    async #save() {
        const quill = this.#quillEditor.instance;
        const descriptionInput = this.#form.querySelector('.DescriptionInput');
        descriptionInput.value = JSON.stringify(quill.getContents());

        const saveButton = this.#form.querySelector('button[type="submit"]');
        Working.start(saveButton);

        // The form goes too, so a refusal about the link or the title is
        // written under that box rather than thrown at the corner of the
        // screen.
        const result = await Api.post('/api/edit-post', {
            postId: this.#postData.postId,
            title: this.#form.querySelector('[name="title"]').value,
            linkURL: this.#form.querySelector('[name="linkURL"]')
                ? this.#form.querySelector('[name="linkURL"]').value
                : '',
            description: descriptionInput.value,
            sensitive: this.#form.querySelector('[name="sensitive"]')?.checked ?? false,
            contentWarning: this.#form.querySelector('[name="contentWarning"]')?.value ?? '',
            altTexts: Object.fromEntries(this.#altInputs.map(({ itemId, input }) => [itemId, input.value.trim()])),
        }, { form: this.#form });

        // Not given back on success: the card is swapped out whole and the
        // button goes with it.
        if (!result) {
            Working.stop(saveButton);

            return;
        }

        this.#onSaveSuccess(result);
    }

    #onSaveSuccess(result) {
        if (!this.#postElement.classList.contains('Post')) return;

        const newContent = Post.fromData(result).postElement();
        this.#postElement.querySelector('.PostContent').replaceWith(newContent);

        this.#postElement.dataset.title = result.title || '';
        this.#postElement.dataset.linkUrl = result.linkURL || '';
        this.#postElement.dataset.descriptionDelta = result.rawDescriptionDelta || '';
        this.#postElement.dataset.hasMedia = result.items.length > 0 && !result.linkURL ? '1' : '';
        this.#postElement.dataset.sensitive = result.sensitive ? '1' : '';

        this.#postElement.style.display = '';
        render_math(newContent);

        // Emoji rendering if available – safe, non‑blocking. Toggled, not just
        // added: an edit can turn an emoji-only post into a text one, which
        // must shed the class as readily as the reverse gains it.
        import('/scripts/EmojiRenderer.js').then(({ EmojiRenderer }) => {
            EmojiRenderer.render(newContent);
            const postBody = this.#postElement.querySelector('.PostBody');
            this.#postElement.classList.toggle('emoji-only', postBody !== null && EmojiRenderer.isEmojiOnly(postBody));
        }).catch(() => {});

        this.#form.remove();
        this.#form = null;
        this.#quillEditor = null;

        Toast.show('Changes saved.');
    }
}

ReadyHandler.add(PostEditor.init);

