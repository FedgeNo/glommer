import { ClientConfig } from '/scripts/ClientConfig.js';
import { Strings } from '/scripts/Strings.js';
import { Toast } from '/scripts/Toast.js';
import { Post } from '/scripts/Post.js';
import { list_item } from '/scripts/utils.js';
import { Cookie } from '/scripts/Cookie.js';
import { render_math } from '/scripts/MathRenderer.js';
import { QuillEditor } from '/scripts/QuillEditor.js';
import { Api } from '/scripts/Api.js';
import { EmojiPicker } from '/scripts/EmojiPicker.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { ToggleButton } from '/scripts/ToggleButton.js';
import { Working } from '/scripts/Working.js';
import { PostFields } from '/scripts/PostFields.js';

export class Composer extends PostFields {
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
            if (event.target.closest('emoji-picker')) return;
            document.querySelectorAll('emoji-picker.Active').forEach(panel => panel.classList.remove('Active'));
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

        // A quote composer (quote.php stamps the reference on the form): the
        // id rides the same FormData as everything else.
        const quotedPostId = form.dataset.quotedPostId;
        if (quotedPostId) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'quotedPostId';
            input.value = quotedPostId;
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
        super();
        this.#form = form;
        this.editorContainer = form.querySelector('.QuillEditor');
        if (!this.editorContainer) {
            throw new Error('Composer: missing .QuillEditor container');
        }

        this.titleInput   = form.querySelector('[name="title"]');
        this.linkInput    = form.querySelector('[name="linkURL"]');
        this.fileInput    = form.querySelector('.ComposerFileInput');
        // What is shown and hidden, since the input itself is out of sight.
        this.filePicker   = form.querySelector('.ComposerFilePicker');
        this.descriptionInput = form.querySelector('.DescriptionInput');
        this.markdownInput  = form.querySelector('.MarkdownInput');
        this.markdownButton = form.querySelector('.MarkdownModeButton');

        /** Whether the textarea is the one being written in. */
        this.markdownMode = false;

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
        this.sensitiveInput = form.querySelector('[name="sensitive"]');
        this.warningInput = form.querySelector('.ContentWarningInput');
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

        for (let index = 0; index < ClientConfig.get('pollMaxOptions'); index++) {
            const option = document.createElement('input');
            option.type = 'text';
            option.className = 'PollOptionInput';
            option.name = 'pollOptions[]';
            option.placeholder = 'Option ' + (index + 1);
            poll.appendWithSpace(Composer.fieldLabel(option, 'Poll Option ' + (index + 1)));
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
        poll.appendWithSpace(Composer.fieldLabel(duration, 'How Long the Poll Runs'));

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

        const [titleLabel, titleInput] = Composer.titleField();
        titleRow.appendWithSpace(titleLabel);
        titleRow.appendWithSpace(titleInput);

        const [linkLabel, linkInput] = Composer.linkField();
        titleRow.appendWithSpace(linkLabel);
        titleRow.appendWithSpace(linkInput);

        fieldset.appendWithSpace(titleRow);

        const editorRow = document.createElement('div');
        editorRow.className = 'EditorRow d-flex gap-2 align-items-start';

        const linkImagePreview = document.createElement('div');
        linkImagePreview.className = 'LinkImagePreview';
        linkImagePreview.style.display = 'none';

        const thumb = document.createElement('img');
        thumb.className = 'LinkImagePreviewThumb';
        thumb.alt = Strings.for('LinkImagePreview', { alt: 'Link preview image' }).alt;
        linkImagePreview.appendWithSpace(thumb);

        const removeLinkBtn = document.createElement('button');
        removeLinkBtn.type = 'button';
        removeLinkBtn.className = 'Button LinkImageRemoveButton Removing';
        removeLinkBtn.textContent = Strings.for('LinkImageRemoveButton', { label: 'Remove Image' }).label;
        linkImagePreview.appendWithSpace(removeLinkBtn);

        const seedInput = document.createElement('input');
        seedInput.type = 'hidden';
        seedInput.name = 'linkImageSeed';
        linkImagePreview.appendWithSpace(seedInput);

        editorRow.appendWithSpace(linkImagePreview);

        const editorColumn = document.createElement('div');
        editorColumn.className = 'EditorColumn';

        // Read out when the writing area is reached, and never shown. A
        // rich-text editor is a contenteditable with a toolbar, which is the
        // hardest thing on this site to use without sight - and the plain text
        // box that writes the same post is one button away, which is worth
        // knowing before struggling with the other one rather than after.
        const editorHelp = document.createElement('p');
        editorHelp.className = 'ComposerEditorHelp visually-hidden';
        editorHelp.id = 'ComposerEditorHelp';
        editorHelp.textContent = 'Rich text editor. If you would rather write in plain text, '
            + 'use the "Use Markdown" button below to swap this for an ordinary text box - '
            + 'the same post, written with markdown for any formatting you want.';
        editorColumn.appendWithSpace(editorHelp);

        const editorContainer = document.createElement('div');
        editorContainer.className = 'QuillEditor';
        editorColumn.appendWithSpace(editorContainer);

        // The other way of writing the same post. Hidden until asked for; the
        // two are never both in play, and whichever is showing is the one the
        // body comes from.
        const markdownInput = document.createElement('textarea');
        markdownInput.className = 'MarkdownInput';
        markdownInput.setAttribute('aria-describedby', 'ComposerMarkdownHelp');
        markdownInput.placeholder = Composer.PLACEHOLDER;
        markdownInput.style.display = 'none';
        editorColumn.appendWithSpace(Composer.fieldLabel(markdownInput, 'Post Text, in Markdown'));
        editorColumn.appendWithSpace(markdownInput);

        const markdownHelp = document.createElement('p');
        markdownHelp.className = 'ComposerMarkdownHelp visually-hidden';
        markdownHelp.id = 'ComposerMarkdownHelp';
        markdownHelp.textContent = 'Plain text. Markdown works here: '
            + 'asterisks around a word make it bold or italic, a hash at the start of a line makes a heading. '
            + 'Use the "Use Rich Text" button below to go back to the formatting toolbar.';
        editorColumn.appendWithSpace(markdownHelp);

        // Says what changed, for anybody who cannot see it change: the mode
        // swapping under them, files arriving, a poll appearing.
        const status = document.createElement('div');
        status.className = 'ComposerStatus visually-hidden';
        status.setAttribute('role', 'status');
        status.setAttribute('aria-live', 'polite');
        editorColumn.appendWithSpace(status);

        editorRow.appendWithSpace(editorColumn);
        fieldset.appendWithSpace(editorRow);

        const descInput = document.createElement('input');
        descInput.type = 'hidden';
        descInput.name = 'description';
        descInput.className = 'DescriptionInput';
        fieldset.appendWithSpace(descInput);

        form.appendWithSpace(fieldset);

        const actions = document.createElement('div');
        actions.className = 'ComposerActions';

        // The buttons flow as one sequence and wrap as one, so what goes into
        // the post fills the line and what to do with it rides the end of it.
        // Only the committing pair is grouped, to keep the two together.
        const commitActions = document.createElement('div');
        commitActions.className = 'ComposerCommitActions';

        const markdownBtn = ToggleButton.build(['Use Markdown', 'Use Rich Text'], 'MarkdownModeButton');
        actions.appendWithSpace(markdownBtn);

        const removeFilesBtn = document.createElement('button');
        removeFilesBtn.type = 'button';
        removeFilesBtn.className = 'Button ComposerFilesRemoveButton Removing';
        removeFilesBtn.style.display = 'none';
        removeFilesBtn.textContent = 'Remove Files';
        actions.appendWithSpace(removeFilesBtn);

        // Named by its class rather than a field name, because it is a picker
        // and not a field: what it holds is moved straight into #attachments
        // and the files are appended from there at submit. A name would put
        // the emptied picker in the form data as a file of its own - one more
        // files[] slot than there are alt texts, which the server reads as a
        // pairing it cannot trust and refuses.
        // The picker's own button reads "Browse..." and that text belongs to
        // the browser - no attribute changes it. So the input is hidden and a
        // label stands in: clicking a label opens the file input it wraps with
        // no script at all, and the input keeps its place in the tab order, so
        // nothing about the keyboard path changes.
        const filePicker = document.createElement('label');
        filePicker.className = 'Button ComposerFilePicker';
        filePicker.appendWithSpace(document.createTextNode('Add Files'));

        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.className = 'ComposerFileInput visually-hidden';
        fileInput.multiple = true;
        fileInput.accept = 'image/*,video/*,audio/*';
        // Contains the words on the button, so what is announced and what is
        // read are the same control rather than two names for one thing.
        fileInput.setAttribute('aria-label', 'Add Files - Images, Video, or Audio');
        filePicker.appendWithSpace(fileInput);

        actions.appendWithSpace(filePicker);

        // Marks the post as something to opt into. A real checkbox, so it rides
        // along in the form's own FormData and there is no toggle state to keep
        // anywhere. Offered on every post: words need warning about at least as
        // often as pictures do, and a spoiler is usually text.
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

        const locationButton = ToggleButton.build(['Add Location', 'Remove Location', 'Locating…'], 'LocationButton');
        actions.appendWithSpace(locationButton);

        const pollButton = ToggleButton.build(['Add Poll', 'Remove Poll'], 'ComposerPollButton');
        actions.appendWithSpace(pollButton);

        // Drafts and scheduling - text/link posts only; #syncFields puts both
        // away when files or a poll are in play, since those publish through
        // paths a StagedPosts row can't carry.
        const scheduleButton = ToggleButton.build(['Add Schedule', 'Remove Schedule'], 'ComposerScheduleButton');
        actions.appendWithSpace(scheduleButton);

        // The clock lives in its own row above the buttons, the way the poll
        // fields do. A date input and a separate, optional time -
        // datetime-local can't say "this day, whenever", and a day alone is
        // a perfectly good schedule (it publishes as the day starts).
        // No d-flex utility: its !important display would overpower the
        // inline display:none that keeps the row away until asked for.
        const scheduleRow = document.createElement('div');
        scheduleRow.className = 'ComposerSchedule';
        scheduleRow.style.display = 'none';

        const scheduleDate = document.createElement('input');
        scheduleDate.type = 'date';
        scheduleDate.className = 'ComposerScheduleDate';
        scheduleRow.appendWithSpace(Composer.fieldLabel(scheduleDate, 'Publish Date'));
        scheduleRow.appendWithSpace(scheduleDate);

        const scheduleTime = document.createElement('input');
        scheduleTime.type = 'time';
        scheduleTime.className = 'ComposerScheduleTime';
        scheduleRow.appendWithSpace(Composer.fieldLabel(scheduleTime, 'Publish Time (Optional)'));
        scheduleRow.appendWithSpace(scheduleTime);

        form.appendWithSpace(scheduleRow);

        // Hidden until asked for. The toggle is bound on the instance rather
        // than here, because opening a poll has to tell the rest of the
        // composer to get out of the way.
        form.appendWithSpace(Composer.pollFieldsToElement());

        // The words to read before the post - under what is being written,
        // with the schedule and the poll, since those are the other things
        // that appear only when asked for. Optional even once asked for:
        // marking a post is a complete answer on its own, and being made to
        // name the thing is a reason not to warn at all.
        const [warningLabel, warningInput] = Composer.contentWarningField();
        warningInput.style.display = 'none';
        form.appendWithSpace(warningLabel);
        form.appendWithSpace(warningInput);

        // EmojiPicker – built and wired by EmojiPicker.setup
        // Last before Post: the two ways of keeping what's written, together
        // at the row's committing end.
        const draftButton = document.createElement('button');
        draftButton.type = 'button';
        draftButton.className = 'Button ComposerDraftButton';
        draftButton.textContent = 'Save Draft';
        commitActions.appendWithSpace(draftButton);

        const submitBtn = ToggleButton.build(['Post', 'Schedule Post', 'Save Draft'], 'ComposerSubmitButton', false);
        submitBtn.type = 'submit';
        commitActions.appendWithSpace(submitBtn);

        actions.appendWithSpace(commitActions);

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
        this.#seedFromDraft();
        this.#syncFields();
    }

    /** Whether this composer is holding a draft rather than a fresh post. */
    #editingDraft() {
        return Boolean(this.#form.dataset.stagedPostId);
    }

    /**
     * The draft the page opened with, put back into the composer it was
     * written in - title, body, link, location and the time it was set to go.
     *
     * The submit button says what it now does. Posting is not it: that would
     * write a second, separate post and leave the draft sitting where it was,
     * so submitting this form saves the draft instead. Publishing stays on
     * the drafts list, which already offers it.
     */
    #seedFromDraft() {
        if (!this.#editingDraft()) return;

        const draft = this.#form.dataset;

        if (this.titleInput) this.titleInput.value = draft.title || '';
        if (this.linkInput) this.linkInput.value = draft.linkUrl || '';

        if (draft.descriptionDelta) {
            try {
                this.#quill.setContents(JSON.parse(draft.descriptionDelta));
            } catch (_) {
                // A delta that will not parse is not worth losing the rest of
                // the draft over; the body simply opens empty.
            }
        }

        if (draft.latitude && draft.longitude) {
            this.#setLocation(Number(draft.latitude), Number(draft.longitude));
        }

        if (draft.publishAtEpoch) {
            this.scheduleButton.click();

            const when = new Date(Number(draft.publishAtEpoch) * 1000);
            const pad = (part) => String(part).padStart(2, '0');

            this.scheduleDate.value = when.getFullYear() + '-' + pad(when.getMonth() + 1) + '-' + pad(when.getDate());
            this.scheduleTime.value = pad(when.getHours()) + ':' + pad(when.getMinutes());
        }

        ToggleButton.select(this.submitButton, 'Save Draft');
        this.draftButton.remove();
        this.draftButton = null;
    }

    #bindStagingButtons() {
        if (!this.draftButton) return;

        // Saving a fresh composer makes a draft, which by definition has no
        // time set. Saving one that is already a draft keeps whatever the
        // clock now says - including nothing, which turns a scheduled post
        // back into a plain draft.
        this.draftButton.addEventListener('click', () => {
            this.#stagePost(this.#editingDraft() ? this.#scheduledEpoch() : null);
        });

        // The Schedule button only reveals or hides the clock - reading
        // "Remove Schedule" while it's out, so an accidental or curious click
        // has its own way back. Committing is the main submit button's job:
        // with the clock out it reads "Schedule Post", disabled until there
        // is both something written and a valid future date to send it to.
        this.scheduleButton.addEventListener('click', () => {
            const hidden = this.scheduleRow.style.display === 'none';
            this.scheduleRow.style.display = hidden ? '' : 'none';
            ToggleButton.select(this.scheduleButton, hidden ? 'Remove Schedule' : 'Add Schedule');
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
        // Marking the post is what brings the warning field out.
        this.sensitiveInput?.addEventListener('change', () => this.#syncFields());
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
        // Whichever mode is showing is where the words are; the other one is
        // holding a copy from before the last switch.
        const body = this.markdownMode
            ? (this.markdownInput?.value ?? '')
            : (this.#quill?.getText() ?? '');

        return body.trim() !== ''
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

        // Editing one, the button only ever saves it - whether it stays a
        // draft or keeps a time is the clock's business, not the button's.
        if (this.#editingDraft()) {
            ToggleButton.select(this.submitButton, 'Save Draft');
            this.submitButton.disabled = !this.#formHasContent()
                || (this.#isScheduling() && !this.#scheduleIsValid());

            return;
        }

        if (this.#isScheduling()) {
            ToggleButton.select(this.submitButton, 'Schedule Post');
            this.submitButton.disabled = !(this.#scheduleIsValid() && this.#formHasContent());
        } else {
            ToggleButton.select(this.submitButton, 'Post');
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
        ToggleButton.select(this.scheduleButton, 'Add Schedule');
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

        // Whichever button set this going - Save Draft on a fresh composer,
        // the submit button on one holding a draft already.
        if (this.draftButton) Working.start(this.draftButton);
        if (this.submitButton) Working.start(this.submitButton);
        Working.start(this.scheduleButton);

        try {
            const staged = {
                title: this.titleInput?.value ?? '',
                description: JSON.stringify(this.#quill.getContents()),
                linkURL: this.linkInput?.value ?? '',
                latitude: this.latitudeInput?.value ?? '',
                longitude: this.longitudeInput?.value ?? '',
                sensitive: false,
                publishAtEpoch: publish_at_epoch,
            };

            if (this.#editingDraft()) {
                staged.stagedPostId = Number(this.#form.dataset.stagedPostId);
            }

            const result = await Api.post(
                this.#editingDraft() ? '/api/update-staged' : '/api/stage-post',
                staged,
                { form: this.#form }
            );

            if (!result) return;

            // Editing happened on a page of its own, so there is nowhere to
            // stay: the list it came from is where the saved draft now is.
            if (this.#editingDraft()) {
                window.location.href = ClientConfig.siteURL() + '/drafts';

                return;
            }

            Toast.show(publish_at_epoch === null
                ? 'Saved to Drafts.'
                : 'Scheduled - see Drafts & Scheduled in the menu.');

            this.#form.reset();
            this.#quill.setText('');
            this.#resetSchedule();
            if (this.locationButton) this.#setLocation(null, null);
            this.#syncFields();
        } finally {
            if (this.draftButton) Working.stop(this.draftButton);
            // Hands the submit button back before the rule below decides
            // whether it should be usable: syncSubmitState sets .disabled
            // straight, so leaving this out would clear the disabling and
            // leave the button pulsing at nothing.
            Working.stop(this.submitButton);
            // Re-imposes the content rule: after a successful save the form
            // is empty again, and an empty form offers no live Save Draft.
            this.#syncSubmitState();
            Working.stop(this.scheduleButton);
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

        if (this.markdownButton) {
            this.markdownButton.addEventListener('click', () => this.#toggleMarkdownMode());
            this.markdownInput?.addEventListener('input', () => this.#syncSubmitState());
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
            trigger.setAttribute('aria-label', Strings.for('EmojiPickerTriggerButton', { label: 'Insert Emoji' }).label);
            trigger.textContent = '🙂';
            wrapper.appendWithSpace(trigger);

            wrapper.appendChild(document.createElement('emoji-picker'));

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
        Composer.#toggle(this.filePicker, !hasLink && !hasPoll && !scheduling);
        Composer.#toggle(this.pollButton, !hasLink && !hasFiles && !scheduling);

        // The warning follows the checkbox rather than the attachments: it is
        // offered whenever the post is marked, and emptied when the mark comes
        // off so a warning cannot ride along on a post nobody flagged.
        const sensitive = Boolean(this.sensitiveInput ?. checked);

        Composer.#toggle(this.warningInput, sensitive);

        if (!sensitive && this.warningInput) {
            this.warningInput.value = '';
        }

        // Drafts and scheduling carry text, link and location only - media
        // publishes through the upload queue and a poll's clock starts when
        // readers can vote, so both put these controls away. And while the
        // schedule clock is out, Save Draft goes away too: the two are ways
        // of NOT posting yet, and offering both at once just crowds the row.
        // A quote can't be staged - StagedPosts carries no reference - so a
        // quote composer offers neither keeping option.
        const quoting = Boolean(this.#form.dataset.quotedPostId);

        Composer.#toggle(this.draftButton, !hasFiles && !hasPoll && !scheduling && !quoting);
        Composer.#toggle(this.scheduleButton, !hasFiles && !hasPoll && !quoting);

        // Unreachable through the controls above (they hide before they could
        // collide), but kept so no code path can ever hold files or a poll
        // AND a schedule at once.
        if ((hasFiles || hasPoll) && this.scheduleDate) {
            this.#resetSchedule();
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
                ToggleButton.select(this.pollButton, 'Remove Poll');
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
        ToggleButton.select(this.pollButton, 'Add Poll');
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

        const preview = await Api.post('/api/link-preview', { url }, { signal: controller.signal });
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
            await Api.post('/api/discard-link-image', { seed });
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
            const [altLabel, altInput] = Composer.altTextField('Alt Text for ' + file.name);
            entry.altInput = altInput;
            row.appendWithSpace(altLabel);
            row.appendWithSpace(altInput);
        }

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'Button ComposerAttachmentRemoveButton Removing ms-auto';
        remove.textContent = 'Remove';
        remove.addEventListener('click', () => this.#removeAttachment(entry));
        row.appendWithSpace(remove);

        this.#attachments.push(entry);
        this.#attachmentList().appendWithSpace(row);

        // A file appearing in a list below is silent, and there is an alt text
        // box that came with it which nobody would know to look for.
        this.#announce(this.#attachments.length === 1
            ? '1 file attached. Each image has a box for describing it.'
            : this.#attachments.length + ' files attached.');
    }

    #removeAttachment(entry) {
        if (entry.thumbURL !== null) {
            URL.revokeObjectURL(entry.thumbURL);
        }

        entry.row.remove();
        this.#attachments = this.#attachments.filter((candidate) => candidate !== entry);

        this.#announce(this.#attachments.length === 0
            ? 'File removed. Nothing attached now.'
            : 'File removed. ' + this.#attachments.length + ' left.');

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

        Working.start(this.locationButton);
        ToggleButton.select(this.locationButton, 'Locating…');

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
        Working.stop(this.locationButton);
        ToggleButton.select(this.locationButton, active ? 'Remove Location' : 'Add Location');
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

            // A form submits on Enter whether or not it still has a button to
            // do it with, and posting is not what this page is for: it would
            // write a second post and leave the draft where it was. Saving is.
            if (this.#editingDraft()) {
                this.#stagePost(this.#scheduledEpoch());

                return;
            }

            this.#submit();
        });
    }

    /**
     * One body converted to the other way of writing it, or null when the
     * server would not. Conversion lives there rather than here so markdown
     * means one thing on this site: the same pair that reads an inbound
     * Fediverse post reads a member's markdown.
     */
    static async #converted(body, to) {
        const result = await Api.post('/api/convert-body', { body, to });

        return result ? result.body : null;
    }

    /** Says what just changed, for anybody who cannot see it change. */
    #announce(text) {
        const status = this.#form.querySelector('.ComposerStatus');

        if (status) status.textContent = text;
    }

    /**
     * Swaps which of the two the post is being written in, carrying the words
     * across. Lossless in the direction that matters - what is stored is the
     * delta, and a delta written out as markdown and read back is the same
     * delta - though markdown typed by hand comes back in the site's own
     * spelling, since *x* and _x_ are one thing and only one can be written.
     */
    async #toggleMarkdownMode() {
        Working.start(this.markdownButton);

        try {
            if (this.markdownMode) {
                const delta = await Composer.#converted(this.markdownInput.value, 'delta');

                if (delta === null) {
                    return;
                }

                this.#quill.setContents(JSON.parse(delta).ops);
            } else {
                const markdown = await Composer.#converted(JSON.stringify(this.#quill.getContents()), 'markdown');

                if (markdown === null) {
                    return;
                }

                this.markdownInput.value = markdown;
            }
        } finally {
            Working.stop(this.markdownButton);
        }

        this.markdownMode = !this.markdownMode;
        this.markdownInput.style.display = this.markdownMode ? '' : 'none';
        this.editorContainer.style.display = this.markdownMode ? 'none' : '';
        ToggleButton.select(this.markdownButton, this.markdownMode ? 'Use Rich Text' : 'Use Markdown');

        // Quill's toolbar is its own element beside the editor, and it means
        // nothing while the textarea is the one being written in.
        const toolbar = this.#form.querySelector('.ql-toolbar');

        if (toolbar) {
            toolbar.style.display = this.markdownMode ? 'none' : '';
        }

        this.#announce(this.markdownMode
            ? 'Now writing in plain text with markdown.'
            : 'Now writing in the rich text editor.');

        // The box they were in has just been hidden, which leaves focus
        // nowhere. Put it in the one that replaced it, at the same job.
        if (this.markdownMode) {
            this.markdownInput.focus();
        } else {
            this.editorContainer.querySelector('.ql-editor')?.focus();
        }

        this.#syncSubmitState();
    }

    async #submit() {
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

        // Whichever mode is showing is the one the post comes from, and the
        // form only ever carries a delta - so a markdown body is converted
        // here rather than the endpoints learning a second format.
        if (this.markdownMode) {
            const delta = await Composer.#converted(this.markdownInput.value, 'delta');

            if (delta === null) {
                return;
            }

            this.descriptionInput.value = delta;
        } else {
            this.descriptionInput.value = JSON.stringify(this.#quill.getContents());
        }

        Working.start(this.submitButton);
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
            Working.stop(this.submitButton);
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
        // A quote page has no feed to drop the new post into - the finished
        // quote's own page is the natural place to land.
        if (this.#form.dataset.quotedPostId && data.response.postId) {
            window.location.href = ClientConfig.siteURL() + '/users/'
                + ClientConfig.get('currentUserUsername') + '/' + data.response.postId;
            return;
        }

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
}

ReadyHandler.add(Composer.init);
