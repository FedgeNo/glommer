import { Strings } from '/scripts/Strings.js';
import { ClientConfig } from '/scripts/ClientConfig.js';
import { Toast } from '/scripts/Toast.js';
import { Post } from '/scripts/Post.js';
import { QuillEditor } from '/scripts/QuillEditor.js';
import { render_math } from '/scripts/MathRenderer.js';
import { Api } from '/scripts/Api.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Working } from '/scripts/Working.js';
import { PostFields } from '/scripts/PostFields.js';

export class PostEditor extends PostFields {
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
        super();
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
        form.className = 'Form PostEditForm';

        const fields = document.createElement('fieldset');

        const titleRow = document.createElement('div');
        titleRow.className = 'PostComposerFields';

        const [titleLabel, titleInput] = PostEditor.titleField(data.title || '');
        titleRow.appendWithSpace(titleLabel);
        titleRow.appendWithSpace(titleInput);

        if (!data.hasMedia) {
            const [linkLabel, linkInput] = PostEditor.linkField(data.linkUrl || '');
            titleRow.appendWithSpace(linkLabel);
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
        altList.className = 'PostEditAltList';

            for (const item of imageItems) {
                const row = document.createElement('li');
        row.className = 'PostEditAltRow';

                const sourceImage = item.querySelector('img');
                const thumb = document.createElement('img');
                thumb.className = 'ComposerAttachmentThumb';
                thumb.src = sourceImage?.currentSrc || sourceImage?.src || sourceImage?.dataset.src || '';
                thumb.alt = '';
                row.appendWithSpace(thumb);

                const [altLabel, altInput] = PostEditor.altTextField(Strings.for('PostEditor').altText || '', item.dataset.altText || '');
                row.appendWithSpace(altLabel);
                row.appendWithSpace(altInput);

                this.#altInputs.push({ itemId: item.dataset.itemId, input: altInput });

                altList.appendWithSpace(row);
            }

            fields.appendWithSpace(altList);
        }

        form.appendWithSpace(fields);

        // Under what is being written, the same place the composer puts it.
        const [warningLabel, warningInput] = PostEditor.contentWarningField(data.contentWarning || '');
        warningInput.style.display = data.sensitive === '1' ? '' : 'none';
        form.appendWithSpace(warningLabel);
        form.appendWithSpace(warningInput);

        const actions = document.createElement('div');
        actions.className = 'PostEditActions';

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
        sensitiveToggle.appendWithSpace(document.createTextNode(Strings.for('PostEditor').sensitive || ''));

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
        cancelButton.textContent = Strings.for('PostEditor').cancel || '';
        cancelButton.addEventListener('click', () => this.#cancel());
        actions.appendWithSpace(cancelButton);

        const saveButton = document.createElement('button');
        saveButton.type = 'submit';
        saveButton.className = 'Button';
        saveButton.textContent = Strings.for('PostEditor').save || '';
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

        Toast.show(Strings.for('PostEditor').saved || '');
    }
}

ReadyHandler.add(PostEditor.init);
