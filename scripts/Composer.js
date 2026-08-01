import { ClientConfig } from '/scripts/ClientConfig.js';
import { Toast } from '/scripts/Toast.js';
import { Post } from '/scripts/Post.js';
import { csrf_headers, list_item } from '/scripts/utils.js';
import { Cookie } from '/scripts/Cookie.js';
import { render_math } from '/scripts/MathRenderer.js';
import { QuillEditor } from '/scripts/QuillEditor.js';
import { Api } from '/scripts/Api.js';
import { EmojiPicker } from '/scripts/EmojiPicker.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';

export class Composer {
    static PLACEHOLDER = "What's on your mind?";

    static #instances = new WeakMap();
    #slot = 'main';
    #quill = null;
    #form = null;
    #autofilledTitle = '';
    #autofilledDescription = '';

    static init() {
        document.addEventListener('click', (event) => {
            if (event.target.closest('.EmojiPickerTriggerButton')) return;
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

    /**
     * Attach coordinates chosen somewhere other than the location button - the
     * map page picks a point by click. Pass nulls to clear it again.
     */
    setLocation(latitude, longitude) {
        this.#setLocation(latitude, longitude);
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
        this.removeFilesButton  = form.querySelector('.ComposerFilesRemoveButton');
        this.progressBar        = form.querySelector('.ProgressBar');

        this.linkImagePreview  = form.querySelector('.LinkImagePreview');
        this.linkImageThumb    = form.querySelector('.LinkImagePreviewThumb');
        this.linkImageSeedInput = form.querySelector('[name="linkImageSeed"]');

        this.locationButton = form.querySelector('.LocationButton');
        this.latitudeInput  = form.querySelector('[name="latitude"]');
        this.longitudeInput = form.querySelector('[name="longitude"]');

        Composer.#instances.set(form, this);
    }

    /**
     * The poll controls: the choices, whether several may be picked, and how
     * long it runs.
     *
     * The durations come from ClientConfig rather than being listed here. The
     * server refuses any duration outside its own set, so a second list in this
     * file would only be a way for the two to disagree.
     */
    static pollFieldsToElement() {
        const poll = document.createElement('fieldset');
        poll.className = 'ComposerPoll';
        poll.style.display = 'none';

        const legend = document.createElement('legend');
        legend.textContent = 'Poll';
        poll.appendWithSpace(legend);

        for (let index = 0; index < (ClientConfig.get('pollMaxOptions') || 4); index++) {
            const option = document.createElement('input');
            option.type = 'text';
            option.className = 'PollOptionInput';
            option.name = 'pollOptions[]';
            option.placeholder = 'Option ' + (index + 1);
            option.setAttribute('aria-label', 'Poll option ' + (index + 1));
            poll.appendWithSpace(option);
        }

        const multiple = document.createElement('label');
        multiple.className = 'PollMultipleToggle';

        const multipleInput = document.createElement('input');
        multipleInput.type = 'checkbox';
        multipleInput.name = 'pollMultiple';
        multipleInput.value = '1';
        multiple.appendWithSpace(multipleInput);
        multiple.appendWithSpace(document.createTextNode('Allow more than one choice'));

        poll.appendWithSpace(multiple);

        const duration = document.createElement('select');
        duration.className = 'PollDurationSelect';
        duration.name = 'pollDuration';
        duration.setAttribute('aria-label', 'How long the poll runs');

        for (const [label, minutes] of Object.entries(ClientConfig.get('pollDurations') || {})) {
            const choice = document.createElement('option');
            choice.value = String(minutes);
            choice.textContent = label;
            duration.appendWithSpace(choice);
        }

        poll.appendWithSpace(duration);

        return poll;
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
        removeLinkBtn.className = 'Button LinkImageRemoveButton';
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
        actions.className = 'd-flex align-items-center gap-2 ms-auto ComposerActions';

        const removeFilesBtn = document.createElement('button');
        removeFilesBtn.type = 'button';
        removeFilesBtn.className = 'Button ComposerFilesRemoveButton';
        removeFilesBtn.style.display = 'none';
        removeFilesBtn.textContent = 'Remove Files';
        actions.appendWithSpace(removeFilesBtn);

        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.name = 'files[]';
        fileInput.multiple = true;
        fileInput.accept = 'image/*,video/*,audio/*';
        fileInput.setAttribute('aria-label', 'Attach images, video, or audio');
        actions.appendWithSpace(fileInput);

        // Optional geolocation: the button toggles between attaching the browser's
        // current position and removing it; the hidden inputs ride along in the
        // form's FormData submission (empty when no location is attached).
        const latitudeInput = document.createElement('input');
        latitudeInput.type = 'hidden';
        latitudeInput.name = 'latitude';
        form.appendWithSpace(latitudeInput);

        const longitudeInput = document.createElement('input');
        longitudeInput.type = 'hidden';
        longitudeInput.name = 'longitude';
        form.appendWithSpace(longitudeInput);

        const locationButton = document.createElement('button');
        locationButton.type = 'button';
        locationButton.className = 'Button LocationButton';
        locationButton.textContent = 'Add Location';
        actions.appendWithSpace(locationButton);

        // EmojiPicker – built and wired by EmojiPicker.setup
        const emojiBtnWrapper = document.createElement('div');
        emojiBtnWrapper.className = 'EmojiPicker';

        const emojiTrigger = document.createElement('button');
        emojiTrigger.type = 'button';
        emojiTrigger.className = 'Button EmojiPickerTriggerButton';
        emojiTrigger.setAttribute('aria-label', 'Insert emoji');
        emojiTrigger.textContent = '🙂';
        emojiBtnWrapper.appendWithSpace(emojiTrigger);

        const emojiPanel = document.createElement('div');
        emojiPanel.className = 'EmojiPickerPanel';
        emojiBtnWrapper.appendWithSpace(emojiPanel);

        actions.appendWithSpace(emojiBtnWrapper);
        EmojiPicker.setup(emojiBtnWrapper);

        // Classifies this post's media as something to opt into seeing. A real
        // checkbox, so it rides along in the form's own FormData and there is
        // no toggle state to keep anywhere.
        const sensitiveToggle = document.createElement('label');
        sensitiveToggle.className = 'SensitiveMediaToggle';

        const sensitiveInput = document.createElement('input');
        sensitiveInput.type = 'checkbox';
        sensitiveInput.name = 'sensitive';
        sensitiveInput.value = '1';
        sensitiveToggle.appendWithSpace(sensitiveInput);
        sensitiveToggle.appendWithSpace(document.createTextNode('Sensitive'));

        actions.appendWithSpace(sensitiveToggle);

        const pollButton = document.createElement('button');
        pollButton.type = 'button';
        pollButton.className = 'Button ComposerPollButton';
        pollButton.textContent = 'Add Poll';
        actions.appendWithSpace(pollButton);

        // Hidden until asked for, and emptied again when withdrawn - the inputs
        // stay in the form either way, so leaving text behind in them would
        // attach a poll the author had just removed.
        const poll = Composer.pollFieldsToElement();
        form.appendWithSpace(poll);

        pollButton.addEventListener('click', () => {
            const adding = poll.style.display === 'none';

            poll.style.display = adding ? '' : 'none';
            pollButton.textContent = adding ? 'Remove Poll' : 'Add Poll';

            if (!adding) {
                for (const input of poll.querySelectorAll('input[name="pollOptions[]"]')) {
                    input.value = '';
                }

                poll.querySelector('input[name="pollMultiple"]').checked = false;
            }
        });

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
        this.#bindLocationButton();
        this.#syncFields();
    }

    #createQuill() {
        const editor = new QuillEditor(this.editorContainer, { placeholder: Composer.PLACEHOLDER });
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
            this.#syncFields();
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

        // What a previous preview filled in, so a field the author hasn't since
        // edited can be replaced by a newer preview while anything they typed
        // themselves is left alone. The composer's own bookkeeping, so it stays
        // on the instance.
        if (preview.title && this.titleInput) {
            const curTitle = this.titleInput.value.trim();
            if (curTitle === '' || curTitle === this.#autofilledTitle) {
                this.titleInput.value = preview.title;
                this.#autofilledTitle = preview.title;
            }
        }

        if (preview.description && this.#quill) {
            const curText = this.#quill.getText().trim();
            if (curText === '' || curText === this.#autofilledDescription) {
                this.#quill.setText(preview.description);
                this.#autofilledDescription = preview.description.trim();
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
            this.#syncFields();
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

        const removeLinkBtn = this.#form.querySelector('.LinkImageRemoveButton');
        if (removeLinkBtn) {
            removeLinkBtn.addEventListener('click', () => this.#discardStagedImage());
        }
    }

    #bindLocationButton() {
        if (!this.locationButton) return;
        this.locationButton.addEventListener('click', () => this.#toggleLocation());
    }

    #toggleLocation() {
        if (this.latitudeInput.value !== '') {
            this.#setLocation(null, null);
            return;
        }

        if (!navigator.geolocation) {
            Toast.show('Your browser can\'t share a location.');
            return;
        }

        this.locationButton.disabled = true;
        this.locationButton.textContent = 'Locating…';

        navigator.geolocation.getCurrentPosition(
            (position) => this.#setLocation(position.coords.latitude, position.coords.longitude),
            () => {
                this.#setLocation(null, null);
                Toast.show('Could not get your location. Check your browser\'s location permission.');
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    }

    #setLocation(latitude, longitude) {
        const active = latitude !== null && longitude !== null;
        this.latitudeInput.value = active ? latitude : '';
        this.longitudeInput.value = active ? longitude : '';
        this.locationButton.disabled = false;
        this.locationButton.textContent = active ? 'Remove Location' : 'Add Location';
        this.locationButton.classList.toggle('Active', active);

        // Announced however the location changed - the button, the map, or a
        // cleared form - so a page showing the same location elsewhere can stay
        // in step with it. The map page uses this to place, move, and drop its
        // pin, which is why the pin follows the Remove Location button too.
        this.#form.dispatchEvent(new CustomEvent('composer:locationchange', {
            bubbles: true,
            detail: { latitude: active ? latitude : null, longitude: active ? longitude : null },
        }));
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
        // Read before reset() blanks the hidden inputs - the map listens for
        // this to drop a permanent pin where the post just landed.
        const latitude = this.latitudeInput ? this.latitudeInput.value : '';
        const longitude = this.longitudeInput ? this.longitudeInput.value : '';

        this.#form.reset();
        this.#quill.setText('');
        this.#syncFields();

        if (this.removeFilesButton) this.removeFilesButton.style.display = 'none';
        if (this.locationButton) this.#setLocation(null, null);
        if (this.linkImagePreview) {
            this.linkImagePreview.style.display = 'none';
            this.linkImageThumb.src = '';
        }
        if (this.linkInput) delete this.linkInput._lastFetchedUrl;

        this.#form.dispatchEvent(new CustomEvent('composer:posted', {
            bubbles: true,
            detail: { post: data.response, latitude, longitude },
        }));

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
