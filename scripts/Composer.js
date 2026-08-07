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

    /**
     * The picked files and their alt texts, in the order they will submit.
     * The file input itself is emptied after every pick - a FileList can't be
     * added to or spliced, so the input would otherwise REPLACE the whole
     * selection on a second pick and could never offer per-file removal.
     * Each entry: { file, row, altInput, thumbURL }.
     */
    #attachments = [];

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

        this.sensitiveToggle = form.querySelector('.SensitiveMediaToggle');
        this.pollButton = form.querySelector('.ComposerPollButton');
        this.pollFields = form.querySelector('.ComposerPoll');
        this.draftButton = form.querySelector('.ComposerDraftButton');
        this.scheduleButton = form.querySelector('.ComposerScheduleButton');
        this.scheduleRow = form.querySelector('.ComposerSchedule');
        this.scheduleDate = form.querySelector('.ComposerScheduleDate');
        this.scheduleTime = form.querySelector('.ComposerScheduleTime');

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
        fieldset.className = 'ComposerFieldset';
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
        removeLinkBtn.className = 'Button LinkImageRemoveButton Removing';
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
        removeFilesBtn.className = 'Button ComposerFilesRemoveButton Removing';
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

        // Classifies this post's media as something to opt into seeing. A real
        // checkbox, so it rides along in the form's own FormData and there is
        // no toggle state to keep anywhere.
        //
        // Placed immediately after the picker because it only ever appears once
        // the picker has gone: it says something about attached files, so it is
        // offered when there are some and stands exactly where the control that
        // attached them was.
        const sensitiveToggle = document.createElement('label');
        sensitiveToggle.className = 'SensitiveMediaToggle';

        const sensitiveInput = document.createElement('input');
        sensitiveInput.type = 'checkbox';
        sensitiveInput.name = 'sensitive';
        sensitiveInput.value = '1';
        sensitiveToggle.appendWithSpace(sensitiveInput);
        sensitiveToggle.appendWithSpace(document.createTextNode('Sensitive'));

        actions.appendWithSpace(sensitiveToggle);

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

        const pollButton = document.createElement('button');
        pollButton.type = 'button';
        pollButton.className = 'Button ComposerPollButton';
        pollButton.textContent = 'Add Poll';
        actions.appendWithSpace(pollButton);

        // Drafts and scheduling - text/link posts only; #syncFields puts both
        // away when files or a poll are in play, since those publish through
        // paths a StagedPosts row can't carry.
        const scheduleButton = document.createElement('button');
        scheduleButton.type = 'button';
        scheduleButton.className = 'Button ComposerScheduleButton';
        scheduleButton.textContent = 'Add Schedule';
        actions.appendWithSpace(scheduleButton);

        // The clock lives in its own row above the buttons, the way the poll
        // fields do. A date input and a separate, optional time -
        // datetime-local can't say "this day, whenever", and a day alone is
        // a perfectly good schedule (it publishes as the day starts).
        const scheduleRow = document.createElement('div');
        scheduleRow.className = 'ComposerSchedule d-flex gap-2 align-items-center';
        scheduleRow.style.display = 'none';

        const scheduleDate = document.createElement('input');
        scheduleDate.type = 'date';
        scheduleDate.className = 'ComposerScheduleDate';
        scheduleDate.setAttribute('aria-label', 'Publish date');
        scheduleRow.appendWithSpace(scheduleDate);

        const scheduleTime = document.createElement('input');
        scheduleTime.type = 'time';
        scheduleTime.className = 'ComposerScheduleTime';
        scheduleTime.setAttribute('aria-label', 'Publish time (optional)');
        scheduleRow.appendWithSpace(scheduleTime);

        form.appendWithSpace(scheduleRow);

        // Hidden until asked for. The toggle is bound on the instance rather
        // than here, because opening a poll has to tell the rest of the
        // composer to get out of the way.
        form.appendWithSpace(Composer.pollFieldsToElement());

        // EmojiPicker – built and wired by EmojiPicker.setup
        // Last before Post: the two ways of keeping what's written, together
        // at the row's committing end.
        const draftButton = document.createElement('button');
        draftButton.type = 'button';
        draftButton.className = 'Button ComposerDraftButton';
        draftButton.textContent = 'Save Draft';
        actions.appendWithSpace(draftButton);

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
        this.#bindPollButton();
        this.#bindStagingButtons();
        this.#syncFields();
    }

    #bindStagingButtons() {
        if (!this.draftButton) return;

        this.draftButton.addEventListener('click', () => this.#stagePost(null));

        // The Schedule button only reveals or hides the clock - reading
        // "Remove Schedule" while it's out, so an accidental or curious click
        // has its own way back. Committing is the main submit button's job:
        // with the clock out it reads "Schedule Post", disabled until there
        // is both something written and a valid future date to send it to.
        this.scheduleButton.addEventListener('click', () => {
            const hidden = this.scheduleRow.style.display === 'none';
            this.scheduleRow.style.display = hidden ? '' : 'none';
            this.scheduleButton.textContent = hidden ? 'Remove Schedule' : 'Add Schedule';
            this.scheduleButton.classList.toggle('Removing', hidden);

            if (hidden) {
                // Yesterday isn't offered - the same rule the server enforces.
                this.scheduleDate.min = new Date().toISOString().slice(0, 10);
            } else {
                this.scheduleDate.value = '';
                this.scheduleTime.value = '';
            }

            this.#syncSubmitState();
            this.#syncFields();
        });

        this.scheduleDate.addEventListener('input', () => this.#syncSubmitState());
        this.scheduleTime.addEventListener('input', () => this.#syncSubmitState());
        this.titleInput?.addEventListener('input', () => this.#syncSubmitState());
        this.linkInput?.addEventListener('input', () => this.#syncSubmitState());
    }

    #isScheduling() {
        return this.scheduleRow !== null && this.scheduleRow.style.display !== 'none';
    }

    /**
     * The chosen publish moment, or null when the clock is hidden or has no
     * date yet. A date alone is enough - it means as that day starts.
     */
    #scheduledEpoch() {
        if (!this.#isScheduling() || this.scheduleDate.value === '') {
            return null;
        }

        return Math.floor(new Date(this.scheduleDate.value + 'T' + (this.scheduleTime.value || '00:00')).getTime() / 1000);
    }

    #scheduleIsValid() {
        const epoch = this.#scheduledEpoch();

        return epoch !== null && epoch * 1000 > Date.now() + 60000;
    }

    #formHasContent() {
        return (this.#quill?.getText().trim() ?? '') !== ''
            || (this.titleInput?.value.trim() ?? '') !== ''
            || (this.linkInput?.value.trim() ?? '') !== '';
    }

    /**
     * The submit button's whole state in one place: what it says, and whether
     * it can be pressed. An empty composer never offers a live button -
     * attached files count as content (a media post needs no words), and
     * scheduling additionally demands a real future moment. No click ever
     * finds out afterwards what was missing.
     */
    #syncSubmitState() {
        if (!this.submitButton) return;

        const has_anything = this.#formHasContent() || this.#attachments.length > 0;

        if (this.#isScheduling()) {
            this.submitButton.textContent = 'Schedule Post';
            this.submitButton.disabled = !(this.#scheduleIsValid() && this.#formHasContent());
        } else {
            this.submitButton.textContent = 'Post';
            this.submitButton.disabled = !has_anything;
        }

        // Same rule for saving a draft: nothing written is nothing to save.
        // Files don't count here - a draft carries text, link and location
        // only.
        if (this.draftButton) {
            this.draftButton.disabled = !this.#formHasContent();
        }
    }

    /** Puts the clock away and every label with it. */
    #resetSchedule() {
        // Already away means nothing to do - this also parts the mutual
        // recursion with #syncFields, which calls here when files or a poll
        // arrive and is called back so the draft button can return.
        if (!this.#isScheduling()) return;

        this.scheduleDate.value = '';
        this.scheduleTime.value = '';
        this.scheduleRow.style.display = 'none';
        this.scheduleButton.textContent = 'Add Schedule';
        this.scheduleButton.classList.remove('Removing');
        this.#syncSubmitState();
        this.#syncFields();
    }

    /**
     * Saves what is written as a draft (no epoch) or a scheduled post - the
     * text/link path only, which #syncFields guarantees by hiding these
     * controls whenever files or a poll are in play.
     */
    async #stagePost(publish_at_epoch) {
        if (!this.#quill) return;

        // Nothing written is nothing to stage - said here, so an empty
        // composer can't appear to succeed at anything.
        if (this.#quill.getText().trim() === ''
            && (this.titleInput?.value.trim() ?? '') === ''
            && (this.linkInput?.value.trim() ?? '') === '') {
            Toast.show('Write something first - there is nothing to save yet.');
            return;
        }

        this.draftButton.disabled = true;
        this.scheduleButton.disabled = true;

        try {
            const result = await Api.post('/api/stage-post', {
                title: this.titleInput?.value ?? '',
                description: JSON.stringify(this.#quill.getContents()),
                linkURL: this.linkInput?.value ?? '',
                latitude: this.latitudeInput?.value ?? '',
                longitude: this.longitudeInput?.value ?? '',
                sensitive: false,
                publishAtEpoch: publish_at_epoch,
            });

            if (!result) return;

            Toast.show(publish_at_epoch === null
                ? 'Saved to Drafts & Scheduled.'
                : 'Scheduled - see Drafts & Scheduled in the menu.');

            this.#form.reset();
            this.#quill.setText('');
            this.#resetSchedule();
            if (this.locationButton) this.#setLocation(null, null);
            this.#syncFields();
        } finally {
            this.draftButton.disabled = false;
            // Re-imposes the content rule: after a successful save the form
            // is empty again, and an empty form offers no live Save Draft.
            this.#syncSubmitState();
            this.scheduleButton.disabled = false;
        }
    }

    #createQuill() {
        const editor = new QuillEditor(this.editorContainer, { placeholder: Composer.PLACEHOLDER });
        this.#quill = editor.instance;

        // While scheduling, the submit button tracks whether there is
        // anything to schedule - which changes with every keystroke here.
        // Guarded: the test runner's Quill stand-in has no event emitter.
        if (typeof this.#quill.on === 'function') {
            this.#quill.on('text-change', () => this.#syncSubmitState());
        }

        // The emoji picker lives in Quill's own toolbar - a bare clickable
        // emoji among the formatting controls, not another button crowding
        // the bottom of the form. Built here because the toolbar only exists
        // once Quill does.
        const toolbar = this.#form.querySelector('.ql-toolbar');

        if (toolbar) {
            const wrapper = document.createElement('span');
            wrapper.className = 'ql-formats EmojiPicker';

            const trigger = document.createElement('button');
            trigger.type = 'button';
            trigger.className = 'EmojiPickerTriggerButton';
            trigger.setAttribute('aria-label', 'Insert emoji');
            trigger.textContent = '🙂';
            wrapper.appendWithSpace(trigger);

            const panel = document.createElement('div');
            panel.className = 'EmojiPickerPanel';
            wrapper.appendWithSpace(panel);

            toolbar.appendWithSpace(wrapper);
            EmojiPicker.setup(wrapper);
        }
    }

    /**
     * A post carries a link, attached media, or a poll - one of the three, and
     * create-post refuses any combination. So the composer offers one at a
     * time: picking any of them takes the other two away rather than letting
     * someone fill in two and be told no on submit.
     */
    #syncFields() {
        // Defaulted before trimming: a missing input is "not chosen", where
        // undefined !== '' would have read as chosen and hidden the other two.
        const hasLink = (this.linkInput?.value ?? '').trim() !== '';
        const hasFiles = this.#attachments.length > 0;
        const hasPoll = this.#pollIsOpen();
        const scheduling = this.#isScheduling();

        Composer.#toggle(this.linkInput, !hasFiles && !hasPoll);
        // The picker stays through having files - that is how more are added;
        // the attachment rows carry their own removal. It goes away while the
        // schedule clock is out (and Add Poll with it): a scheduled post can't
        // carry either, and choosing one must never silently eat the other.
        Composer.#toggle(this.fileInput, !hasLink && !hasPoll && !scheduling);
        Composer.#toggle(this.pollButton, !hasLink && !hasFiles && !scheduling);

        // Only ever alongside attached files, since covering media is all it
        // does. Cleared as it goes, so a classification cannot outlive the
        // files it was about and ride along on a post with none.
        Composer.#toggle(this.sensitiveToggle, hasFiles);

        // Drafts and scheduling carry text, link and location only - media
        // publishes through the upload queue and a poll's clock starts when
        // readers can vote, so both put these controls away. And while the
        // schedule clock is out, Save Draft goes away too: the two are ways
        // of NOT posting yet, and offering both at once just crowds the row.
        Composer.#toggle(this.draftButton, !hasFiles && !hasPoll && !scheduling);
        Composer.#toggle(this.scheduleButton, !hasFiles && !hasPoll);

        // Unreachable through the controls above (they hide before they could
        // collide), but kept so no code path can ever hold files or a poll
        // AND a schedule at once.
        if ((hasFiles || hasPoll) && this.scheduleDate) {
            this.#resetSchedule();
        }

        if (!hasFiles && this.sensitiveToggle !== null) {
            this.sensitiveToggle.querySelector('[name="sensitive"]').checked = false;
        }

        // Content arriving or leaving by any route above changes what the
        // committing buttons may offer.
        this.#syncSubmitState();
    }

    static #toggle(element, visible) {
        if (element) {
            element.style.display = visible ? '' : 'none';
        }
    }

    #pollIsOpen() {
        return this.pollFields !== null && this.pollFields.style.display !== 'none';
    }

    #bindPollButton() {
        if (!this.pollButton || !this.pollFields) return;

        this.pollButton.addEventListener('click', () => {
            if (this.#pollIsOpen()) {
                this.#closePoll();
            } else {
                this.pollFields.style.display = '';
                this.pollButton.textContent = 'Remove Poll';
                this.pollButton.classList.add('Removing');
            }

            this.#syncFields();
        });
    }

    /**
     * Shuts the poll and empties it. The inputs stay in the form either way, so
     * text left behind in them would attach a poll the author had just taken
     * back - and form.reset() cannot help, since which of the three a post is
     * lives in what is shown rather than in any field's value.
     */
    #closePoll() {
        if (!this.pollButton || !this.pollFields) return;

        this.pollFields.style.display = 'none';
        this.pollButton.textContent = 'Add Poll';
        this.pollButton.classList.remove('Removing');

        for (const option of this.pollFields.querySelectorAll('[name="pollOptions[]"]')) {
            option.value = '';
        }

        const multiple = this.pollFields.querySelector('[name="pollMultiple"]');

        if (multiple) {
            multiple.checked = false;
        }
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

    /**
     * The most files one post accepts - the ceiling the server's
     * max_file_uploads is provisioned for (EnvironmentChecker demands 100+).
     * Enforced here as well because the attachment list accumulates across
     * picks: without a client cap, pick 60 twice and the server would
     * silently truncate the batch.
     */
    static MAX_FILES = 100;

    #bindFileChange() {
        if (!this.fileInput || !this.removeFilesButton) return;
        this.fileInput.addEventListener('change', () => {
            for (const file of this.fileInput.files) {
                if (this.#attachments.length >= Composer.MAX_FILES) {
                    Toast.show('A post can carry at most ' + Composer.MAX_FILES + ' files - the rest were not added.');
                    break;
                }

                this.#addAttachment(file);
            }

            // The files now live in #attachments; emptied so the same file can
            // be picked again after a remove, and so the input contributes
            // nothing of its own to the form's FormData at submit.
            this.fileInput.value = '';

            this.removeFilesButton.style.display =
                this.#attachments.length === 0 ? 'none' : '';
            this.#syncFields();
        });
    }

    /**
     * The list the picked files live in, one row each: a thumbnail for an
     * image (with a field for its alt text - what a screen reader will say in
     * the picture's place), a bare name for video/audio, and a remove button.
     * Created when the first attachment needs it and removed with the last,
     * so an empty list is never in the page.
     */
    #attachmentList() {
        let list = this.#form.querySelector('.ComposerAttachmentList');

        if (!list) {
            list = document.createElement('ul');
            list.className = 'ComposerAttachmentList d-flex flex-column gap-2';
            this.#form.querySelector('.ComposerActions').before(list);
        }

        return list;
    }

    #addAttachment(file) {
        const row = document.createElement('li');
        row.className = 'ComposerAttachment d-flex align-items-center gap-2';

        const entry = { file, row, altInput: null, thumbURL: null };

        if (file.type.startsWith('image/')) {
            // Best-effort: a preview that cannot be built must never cost the
            // attachment itself.
            try {
                entry.thumbURL = URL.createObjectURL(file);
            } catch (_) {}

            if (entry.thumbURL !== null) {
                const thumb = document.createElement('img');
                thumb.className = 'ComposerAttachmentThumb';
                thumb.src = entry.thumbURL;
                thumb.alt = '';
                row.appendWithSpace(thumb);
            }
        }

        const name = document.createElement('span');
        name.className = 'ComposerAttachmentName text-sm';
        name.textContent = file.name;
        row.appendWithSpace(name);

        if (file.type.startsWith('image/')) {
            entry.altInput = document.createElement('input');
            entry.altInput.type = 'text';
            entry.altInput.className = 'ComposerAttachmentAltInput';
            entry.altInput.placeholder = 'Alt text - describe this image';
            entry.altInput.maxLength = 1000;
            entry.altInput.setAttribute('aria-label', 'Alt text for ' + file.name);
            row.appendWithSpace(entry.altInput);
        }

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'Button ComposerAttachmentRemoveButton Removing ms-auto';
        remove.textContent = 'Remove';
        remove.addEventListener('click', () => this.#removeAttachment(entry));
        row.appendWithSpace(remove);

        this.#attachments.push(entry);
        this.#attachmentList().appendWithSpace(row);
    }

    #removeAttachment(entry) {
        if (entry.thumbURL !== null) {
            URL.revokeObjectURL(entry.thumbURL);
        }

        entry.row.remove();
        this.#attachments = this.#attachments.filter((candidate) => candidate !== entry);

        if (this.#attachments.length === 0) {
            this.#form.querySelector('.ComposerAttachmentList')?.remove();

            if (this.removeFilesButton) {
                this.removeFilesButton.style.display = 'none';
            }
        }

        this.#syncFields();
    }

    #clearAttachments() {
        for (const entry of [...this.#attachments]) {
            this.#removeAttachment(entry);
        }
    }

    #bindRemoveButtons() {
        if (this.removeFilesButton) {
            this.removeFilesButton.addEventListener('click', () => {
                this.#clearAttachments();
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
        this.locationButton.classList.toggle('Removing', active);

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

        // A picked publish time turns the submit into a scheduling - the
        // button already says "Schedule" when this path is live.
        const scheduled_epoch = this.#scheduledEpoch();

        if (scheduled_epoch !== null) {
            if (scheduled_epoch * 1000 <= Date.now() + 60000) {
                Toast.show('The publish time has to be in the future.');
                return;
            }

            this.#stagePost(scheduled_epoch);
            return;
        }

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

        // The files live in #attachments, not in the (always empty) file
        // input, so they are appended here - files[] and altTexts[] in the
        // same order, one alt entry per file, which is exactly the positional
        // pairing the server reads them back by.
        const formData = new FormData(this.#form);

        for (const entry of this.#attachments) {
            formData.append('files[]', entry.file);
            formData.append('altTexts[]', entry.altInput?.value.trim() ?? '');
        }

        xhr.send(formData);
    }

    #onSubmitSuccess(data) {
        // Read before reset() blanks the hidden inputs - the map listens for
        // this to drop a permanent pin where the post just landed.
        const latitude = this.latitudeInput ? this.latitudeInput.value : '';
        const longitude = this.longitudeInput ? this.longitudeInput.value : '';

        this.#form.reset();
        this.#quill.setText('');
        this.#closePoll();
        this.#clearAttachments();
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
