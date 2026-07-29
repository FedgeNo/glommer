import { ClientConfig } from '/scripts/ClientConfig.js';
import { Toast } from '/scripts/Toast.js';
import { Post } from '/scripts/Post.js';
import { QuillEditor } from '/scripts/QuillEditor.js';
import { render_math } from '/scripts/math.js';
import { csrf_headers } from '/scripts/utils.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';

export class PostEditor {
    #postElement;
    #postData;
    #form;
    #quillEditor;

    /**
     * Bind the delegated click for edit buttons.
     */
    static init() {
        document.addEventListener('click', (event) => {
            const button = event.target.closest('.EditButton');
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
        form.className = 'PostEditForm Card d-flex flex-column gap-2';

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
        editorContainer.dataset.placeholder = 'Edit your post...';
        editorColumn.appendWithSpace(editorContainer);

        fields.appendWithSpace(editorColumn);

        const descriptionInput = document.createElement('input');
        descriptionInput.type = 'hidden';
        descriptionInput.className = 'DescriptionInput';
        descriptionInput.name = 'description';
        fields.appendWithSpace(descriptionInput);

        form.appendWithSpace(fields);

        const actions = document.createElement('div');
        actions.className = 'd-flex align-items-center gap-2 ms-auto';

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

        const editor = new QuillEditor(editorContainer);
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
        saveButton.disabled = true;

        try {
            const response = await fetch(
                ClientConfig.siteURL() + '/api/edit-post',
                {
                    method: 'POST',
                    headers: csrf_headers({ 'Content-Type': 'application/json' }),
                    body: JSON.stringify({
                        postId: this.#postData.postId,
                        title: this.#form.querySelector('[name="title"]').value,
                        linkURL: this.#form.querySelector('[name="linkURL"]')
                            ? this.#form.querySelector('[name="linkURL"]').value
                            : '',
                        description: descriptionInput.value,
                    }),
                }
            );

            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                Toast.show(data.error || 'Could not save changes. Please try again.');
                saveButton.disabled = false;
                return;
            }

            const data = await response.json();
            this.#onSaveSuccess(data.response);
        } catch (error) {
            Toast.show('Network error. Please check your connection and try again.');
            saveButton.disabled = false;
        }
    }

    #onSaveSuccess(result) {
        if (!this.#postElement.classList.contains('Post')) return;

        const newContent = Post.fromData(result).postElement();
        this.#postElement.querySelector('.PostContent').replaceWith(newContent);

        this.#postElement.dataset.title = result.title || '';
        this.#postElement.dataset.linkUrl = result.linkURL || '';
        this.#postElement.dataset.descriptionDelta = result.rawDescriptionDelta || '';
        this.#postElement.dataset.hasMedia = result.items.length > 0 ? '1' : '';

        this.#postElement.style.display = '';
        render_math(newContent);

        // Emoji rendering if available – safe, non‑blocking
        import('/scripts/EmojiRenderer.js').then(({ EmojiRenderer }) => {
            EmojiRenderer.render(newContent);
            const postBody = this.#postElement.querySelector('.PostBody');
            if (postBody && EmojiRenderer.isEmojiOnly(postBody)) {
                this.#postElement.classList.add('emoji-only');
            }
        }).catch(() => {});

        this.#form.remove();
        this.#form = null;
        this.#quillEditor = null;

        Toast.show('Changes saved.');
    }
}

ReadyHandler.add(PostEditor.init);

