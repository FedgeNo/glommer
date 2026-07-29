import { ClientConfig } from '/scripts/ClientConfig.js';
import { Toast } from '/scripts/Toast.js';
import { Post } from '/scripts/Post.js';
import { csrf_headers, list_item } from '/scripts/utils.js';
import { Cookie } from '/scripts/Cookie.js';
import { render_math } from '/scripts/math.js';
import { QuillEditor } from '/scripts/QuillEditor.js';
import { Api } from '/scripts/Api.js';
import { EmojiPicker } from '/scripts/EmojiPicker.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';

export class Composer {
    static #instances = new WeakMap();
    #slot = 'main';
    #quill = null;
    #form = null;

    static init() {
        document.addEventListener('click', (event) => {
            if (event.target.closest('.EmojiTriggerButton')) return;
            if (event.target.closest('.EmojiPickerPanel')) return;
            document.querySelectorAll('.EmojiPickerPanel.Active').forEach(panel => panel.classList.remove('Active'));
        });

        const main = document.querySelector('.PostComposer');
        if (main) Composer.mount(main, 'main');

        const reply = document.querySelector('.ReplyComposer');
        if (reply) Composer.mount(reply, 'reply');
    }

    static mount(form, slot = 'main') {
        if (ClientConfig.get('currentUserId') === null) return;

        const legendText = slot === 'reply' ? 'Reply to this post' : 'Create a post';

        form.replaceChildren();
        Composer.#buildEditor(form, legendText);

        const parentId = form.dataset.parentId;
        if (parentId) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'parentId';
            input.value = parentId;
            form.appendWithSpace(input);
        }

        const instance = new Composer(form);
        instance.#slot = slot;
        instance.#init();
    }

    static getInstance(form) {
        return Composer.#instances.get(form) || null;
    }

    constructor(form) {
        this.#form = form;
        this.editorContainer = form.querySelector('.QuillEditor');
        if (!this.editorContainer) {
            throw new Error('Composer: missing .QuillEditor container');
        }

        this.titleInput   = form.querySelector('[name="title"]');
        this.linkInput    = form.querySelector('[name="linkURL"]');
        this.fileInput    = form.querySelector('[name="files[]"]');
        this.descriptionInput = form.querySelector('.DescriptionInput');

        this.submitButton       = form.querySelector('button[type="submit"]');
        this.removeFilesButton  = form.querySelector('.RemoveFilesButton');
        this.progressBar        = form.querySelector('.ProgressBar');

        this.linkImagePreview  = form.querySelector('.LinkImagePreview');
        this.linkImageThumb    = form.querySelector('.LinkImagePreviewThumb');
        this.linkImageSeedInput = form.querySelector('[name="linkImageSeed"]');

        Composer.#instances.set(form, this);
    }

    static #buildEditor(form, legendText) {
        const fieldset = document.createElement('fieldset');

        const legend = document.createElement('legend');
        legend.textContent = legendText;
        fieldset.appendWithSpace(legend);

        const titleRow = document.createElement('div');
        titleRow.className = 'PostComposerFields d-flex gap-2';

        const titleInput = document.createElement('input');
        titleInput.type = 'text';
        titleInput.name = 'title';
        titleInput.placeholder = 'Title (optional)';
        titleInput.maxLength = 255;
        titleInput.setAttribute('aria-label', 'Title (optional)');
        titleRow.appendWithSpace(titleInput);

        const linkInput = document.createElement('input');
        linkInput.type = 'text';
        linkInput.name = 'linkURL';
        linkInput.placeholder = 'Link (optional)';
        linkInput.maxLength = 255;
        linkInput.setAttribute('aria-label', 'Link (optional)');
        titleRow.appendWithSpace(linkInput);

        fieldset.appendWithSpace(titleRow);

        const editorRow = document.createElement('div');
        editorRow.className = 'EditorRow d-flex gap-2 align-items-start';

        const linkImagePreview = document.createElement('div');
        linkImagePreview.className = 'LinkImagePreview';
        linkImagePreview.style.display = 'none';

        const thumb = document.createElement('img');
        thumb.className = 'LinkImagePreviewThumb';
        thumb.alt = 'Link preview image';
        linkImagePreview.appendWithSpace(thumb);

        const removeLinkBtn = document.createElement('button');
        removeLinkBtn.type = 'button';
        removeLinkBtn.className = 'Button RemoveLinkImageButton';
        removeLinkBtn.textContent = 'Remove image';
        linkImagePreview.appendWithSpace(removeLinkBtn);

        const seedInput = document.createElement('input');
        seedInput.type = 'hidden';
        seedInput.name = 'linkImageSeed';
        linkImagePreview.appendWithSpace(seedInput);

        editorRow.appendWithSpace(linkImagePreview);

        const editorColumn = document.createElement('div');
        editorColumn.className = 'EditorColumn';

        const editorContainer = document.createElement('div');
        editorContainer.className = 'QuillEditor';
        editorContainer.dataset.placeholder = "What's on your mind?";
        editorColumn.appendWithSpace(editorContainer);

        editorRow.appendWithSpace(editorColumn);
        fieldset.appendWithSpace(editorRow);

        const descInput = document.createElement('input');
        descInput.type = 'hidden';
        descInput.name = 'description';
        descInput.className = 'DescriptionInput';
        fieldset.appendWithSpace(descInput);

        form.appendWithSpace(fieldset);

        const actions = document.createElement('div');
        actions.className = 'd-flex align-items-center gap-2 ms-auto';

        const removeFilesBtn = document.createElement('button');
        removeFilesBtn.type = 'button';
        removeFilesBtn.className = 'Button RemoveFilesButton';
        removeFilesBtn.style.display = 'none';
        removeFilesBtn.textContent = 'Remove Files';
        actions.appendWithSpace(removeFilesBtn);

        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.name = 'files[]';
        fileInput.id = 'files';
        fileInput.multiple = true;
        fileInput.accept = 'image/*,video/*,audio/*';
        fileInput.setAttribute('aria-label', 'Attach images, video, or audio');
        actions.appendWithSpace(fileInput);

        // EmojiPickerButton – built and wired by EmojiPicker.setup
        const emojiBtnWrapper = document.createElement('div');
        emojiBtnWrapper.className = 'EmojiPickerButton';

        const emojiTrigger = document.createElement('button');
        emojiTrigger.type = 'button';
        emojiTrigger.className = 'Button EmojiTriggerButton';
        emojiTrigger.setAttribute('aria-label', 'Insert emoji');
        emojiTrigger.textContent = '🙂';
        emojiBtnWrapper.appendWithSpace(emojiTrigger);

        const emojiPanel = document.createElement('div');
        emojiPanel.className = 'EmojiPickerPanel';
        emojiBtnWrapper.appendWithSpace(emojiPanel);

        actions.appendWithSpace(emojiBtnWrapper);
        EmojiPicker.setup(emojiBtnWrapper);

        const submitBtn = document.createElement('button');
        submitBtn.type = 'submit';
        submitBtn.className = 'Button';
        submitBtn.textContent = 'Post';
        actions.appendWithSpace(submitBtn);

        form.appendWithSpace(actions);

        const progress = document.createElement('progress');
        progress.className = 'ProgressBar';
        progress.value = 0;
        progress.max = 100;
        form.appendWithSpace(progress);
    }

    #init() {
        this.#createQuill();
        this.#bindSubmit();
        this.#bindLinkPreview();
        this.#bindFileChange();
        this.#bindRemoveButtons();
        this.#syncFields();
    }

    #createQuill() {
        const editor = new QuillEditor(this.editorContainer);
        this.#quill = editor.instance;
    }

    #syncFields() {
        const hasLink = this.linkInput?.value.trim() !== '';
        const hasFiles = this.fileInput?.files.length > 0;
        if (this.fileInput) this.fileInput.style.display = hasLink ? 'none' : '';
        if (this.linkInput) this.linkInput.style.display = hasFiles ? 'none' : '';
    }

    #bindLinkPreview() {
        if (!this.linkInput) return;
        this.linkInput.addEventListener('input', (event) => {
            clearTimeout(this.linkInput._debounceId);
            const delay = event.inputType === 'insertFromPaste' ? 0 : 500;
            this.linkInput._debounceId = setTimeout(() => this.#fetchLinkPreview(), delay);
        });
    }

    async #fetchLinkPreview() {
        const url = this.linkInput.value.trim();
        if (url === this.linkInput._lastFetchedUrl) return;
        this.linkInput._previewAbortController?.abort();
        const controller = new AbortController();
        this.linkInput._previewAbortController = controller;

        await this.#discardStagedImage();

        if (!url) {
            this.linkInput._lastFetchedUrl = url;
            return;
        }

        const preview = await this.#apiPost('/api/link-preview', { url }, { signal: controller.signal });
        if (!preview || this.linkInput.value.trim() !== url) return;

        this.linkInput._lastFetchedUrl = url;

        if (preview.title && this.titleInput) {
            const curTitle = this.titleInput.value.trim();
            if (curTitle === '' || curTitle === (this.titleInput.dataset.autofilled ?? '')) {
                this.titleInput.value = preview.title;
                this.titleInput.dataset.autofilled = preview.title;
            }
        }

        if (preview.description && this.#quill) {
            const curText = this.#quill.getText().trim();
            const prevAutofill = this.#form.dataset.autofilledDescription ?? '';
            if (curText === '' || curText === prevAutofill) {
                this.#quill.setText(preview.description);
                this.#form.dataset.autofilledDescription = preview.description.trim();
            }
        }

        if (preview.image) {
            this.#showLinkImagePreview(preview.image);
        }
    }

    #showLinkImagePreview(image) {
        if (!this.linkImagePreview) return;
        this.linkImageThumb.src = image.thumbnailURL;
        this.linkImageSeedInput.value = image.seed;
        this.linkImagePreview.style.display = '';
    }

    async #discardStagedImage() {
        if (!this.linkImagePreview) return;
        const seed = this.linkImageSeedInput.value;
        this.linkImageSeedInput.value = '';
        this.linkImagePreview.style.display = 'none';
        this.linkImageThumb.src = '';
        if (seed) {
            await this.#apiPost('/api/discard-link-image', { seed });
        }
    }

    #bindFileChange() {
        if (!this.fileInput || !this.removeFilesButton) return;
        this.fileInput.addEventListener('change', () => {
            this.removeFilesButton.style.display =
                this.fileInput.files.length === 0 ? 'none' : '';
        });
    }

    #bindRemoveButtons() {
        if (this.removeFilesButton) {
            this.removeFilesButton.addEventListener('click', () => {
                if (!this.fileInput) return;
                this.fileInput.value = '';
                this.fileInput.dispatchEvent(new Event('change', { bubbles: true }));
            });
        }

        const removeLinkBtn = this.#form.querySelector('.RemoveLinkImageButton');
        if (removeLinkBtn) {
            removeLinkBtn.addEventListener('click', () => this.#discardStagedImage());
        }
    }

    #bindSubmit() {
        this.#form.addEventListener('submit', (event) => {
            event.preventDefault();
            this.#submit();
        });
    }

    #submit() {
        if (!this.#quill) return;
        this.descriptionInput.value = JSON.stringify(this.#quill.getContents());

        this.submitButton.disabled = true;
        this.progressBar.value = 0;
        this.progressBar.classList.add('Active');

        const xhr = new XMLHttpRequest();
        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                this.progressBar.max = e.total;
                this.progressBar.value = e.loaded;
            }
        });

        xhr.addEventListener('loadend', () => {
            this.submitButton.disabled = false;
            this.progressBar.classList.remove('Active');
            this.progressBar.value = 0;

            const getErrorMsg = (responseText) => {
                try {
                    const data = JSON.parse(responseText);
                    return data.error || null;
                } catch (_) {
                    return null;
                }
            };

            if (xhr.status < 200 || xhr.status >= 300) {
                const msg = getErrorMsg(xhr.responseText) || 'Could not submit the post. Please try again.';
                Toast.show(msg);
                return;
            }

            let data;
            try {
                data = JSON.parse(xhr.responseText);
            } catch (error) {
                console.error('Composer: invalid JSON response', xhr.responseText);
                Toast.show('Something went wrong. Please try again.');
                return;
            }

            this.#onSubmitSuccess(data);
        });

        xhr.open('POST', ClientConfig.siteURL() + '/api/create-post');
        xhr.setRequestHeader('X-CSRF-Token', Cookie.get('CSRF-TOKEN'));
        xhr.send(new FormData(this.#form));
    }

    #onSubmitSuccess(data) {
        this.#form.reset();
        this.#quill.setText('');
        this.#syncFields();

        if (this.removeFilesButton) this.removeFilesButton.style.display = 'none';
        if (this.linkImagePreview) {
            this.linkImagePreview.style.display = 'none';
            this.linkImageThumb.src = '';
        }
        if (this.linkInput) delete this.linkInput._lastFetchedUrl;

        if (data.response.processing) {
            Toast.show("Your media files are processing and you will be notified when they're ready to view. It's safe to leave this page.");
            return;
        }

        const post = Post.fromData(data.response);
        const element = post.toElement();

        if (this.#slot === 'reply') {
            const replyList = document.querySelector('.ReplyList');
            if (replyList) {
                if (!document.querySelector('.RepliesHeading')) {
                    const heading = document.createElement('h2');
                    heading.className = 'RepliesHeading fw-bold text-lg';
                    heading.textContent = 'Replies';
                    replyList.insertAdjacentElement('beforebegin', heading);
                }
                replyList.insertBeforeWithSpace(list_item(element), replyList.firstChild);
            }
        } else {
            this.#form.after(element);
        }

        render_math(element);
    }

    async #apiPost(path, payload, { signal } = {}) {
        try {
            const response = await fetch(ClientConfig.siteURL() + path, {
                method: 'POST',
                headers: csrf_headers({ 'Content-Type': 'application/json' }),
                body: JSON.stringify(payload),
                signal,
            });
            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                Toast.show(data.error || 'Something went wrong. Please try again.');
                return null;
            }
            const data = await response.json();
            return data.response;
        } catch (error) {
            if (error.name !== 'AbortError') {
                Toast.show('Network error. Please check your connection and try again.');
            }
            return null;
        }
    }
}

ReadyHandler.add(Composer.init);
