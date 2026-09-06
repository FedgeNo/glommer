import {
    Api,
    ClickHandler,
    ClientConfig,
    Cookie,
    DOMUtils,
    DateFormat,
    FormErrors,
    ReadyHandler,
    Strings,
    Toast,
    Working,
    csrf_headers,
    list_in,
    list_item,
    page_language,
    parse_server_date,
    sync_theme_color,
    truncate,
} from '/scripts/Runtime.js';
import {
    Anchor,
    Article,
    Avatar,
    AvatarImage,
    AvatarInitial,
    BannedUser,
    Button,
    DeltaRenderer,
    Dialog,
    Div,
    EmojiRenderer,
    Entity,
    HTMLObject,
    Image,
    Linkifier,
    MathRenderer,
    Message,
    MessageCrypto,
    Notification,
    OtherUser,
    Poll,
    Post,
    PostRepostButton,
    ReceivedFriendRequest,
    RelativeTime,
    Report,
    SkinTone,
    Span,
    Time,
    ToggleButton,
    User,
    UserBio,
    enhanceCodeBlocks,
    expand,
    expandInDOM,
    render_formulas,
    render_math,
} from '/scripts/HTMLObjects.js';


// Coordinates.js
const CoordinatesModule = (() => {
/**
 * Client twin of Coordinates.php: a latitude/longitude pair parsed from
 * untrusted input, with the same both-or-neither rule. A pair where one half
 * failed to parse would put a point on the equator rather than nowhere, which
 * is worse than having no point at all.
 */
class Coordinates {
    constructor(latitude, longitude) {
        this.latitude = latitude;
        this.longitude = longitude;
    }

    /** The pair, or null if either side is missing or out of range. */
    static parse(latitude, longitude) {
        if (latitude === undefined || latitude === null || latitude === ''
            || longitude === undefined || longitude === null || longitude === '') {
            return null;
        }

        latitude = Number(latitude);
        longitude = Number(longitude);

        if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
            return null;
        }

        if (latitude < -90 || latitude > 90 || longitude < -180 || longitude > 180) {
            return null;
        }

        return new Coordinates(latitude, longitude);
    }
}

    return { Coordinates };
})();
export const Coordinates = CoordinatesModule.Coordinates;

// QuillEditor.js
const QuillEditorModule = (() => {
class QuillEditor {
    #quill = null;
    #container = null;

    /**
     * @param {HTMLElement} container – the .QuillEditor div
     * @param {object} [options] – optional Quill configuration overrides,
     *     including the placeholder, which differs per composer
     */
    constructor(container, options = {}) {
        this.#container = container;

        const defaultOptions = {
            theme: 'snow',
            modules: {
                toolbar: [
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ header: 1 }, { header: 2 }, { header: 3 }],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    ['blockquote', 'code-block', 'code'],
                    ['link', 'formula'],
                    ['clean'],
                ],
            },
        };

        // Merge options shallowly, letting the caller override toolbar etc.
        const merged = { ...defaultOptions, ...options };

        this.#quill = new Quill(container, merged);
        container.__quill = this.#quill;

        this.#addTooltips();
        this.#nameTheWritingArea();
    }

    /**
     * Gives the writing surface a name and says what it is.
     *
     * Quill builds a contenteditable div. Unnamed, a screen reader reaching it
     * announces an edit area and nothing about what goes in it - and gives no
     * hint that the plain-text alternative beside it exists, which for anybody
     * who finds a rich-text toolbar unusable is the whole answer.
     */
    #nameTheWritingArea() {
        const area = this.#container.querySelector('.ql-editor');

        if (!area) return;

        area.setAttribute('aria-label', this.#container.dataset.editorLabel || Strings.for('QuillEditor').postText || '');

        const help = this.#container
            .closest('form')
            ?.querySelector('.ComposerEditorHelp');

        if (help) {
            if (!help.id) help.id = 'ComposerEditorHelp';
            area.setAttribute('aria-describedby', help.id);
        }
    }

    /** The Quill instance. */
    get instance() {
        return this.#quill;
    }

    /** The container element. */
    get container() {
        return this.#container;
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    #addTooltips() {
        const toolbar = this.#quill.getModule('toolbar')?.container;
        if (!toolbar) return;

        const words = Strings.for('QuillEditor');
        const titles = {
            'ql-bold': words.bold,
            'ql-italic': words.italic,
            'ql-underline': words.underline,
            'ql-strike': words.strikethrough,
            'ql-blockquote': words.blockquote,
            'ql-code-block': words.codeBlock,
            'ql-code': words.inlineCode,
            'ql-link': words.link,
            'ql-formula': words.formula,
            'ql-clean': words.clearFormatting,
        };

        Object.entries(titles).forEach(([cls, title]) => {
            const btn = toolbar.querySelector('button.' + cls);
            if (btn) btn.title = title;
        });

        toolbar.querySelectorAll('button.ql-header[value]').forEach(btn => {
            btn.title = (words.heading || '').replace('{count}', btn.getAttribute('value'));
        });

        const listTitles = { ordered: words.numberedList, bullet: words.bulletList };
        toolbar.querySelectorAll('button.ql-list[value]').forEach(btn => {
            btn.title = listTitles[btn.getAttribute('value')] || words.list || '';
        });
    }
}

    return { QuillEditor };
})();
export const QuillEditor = QuillEditorModule.QuillEditor;

// PostFields.js
const PostFieldsModule = (() => {
/**
 * The fields a post is written with, shared by the composer and the post
 * editor. One definition of each control, so the two ways of writing a post
 * cannot drift apart in names, caps, or what assistive tech is told.
 *
 * Every input is named by a real label, visually hidden: read out, never laid
 * out. The label stands immediately before its input under the same parent,
 * tied to it by a generated id, so nothing about the visible layout moves.
 */
class PostFields {
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
        const words = Strings.for('PostFields');
        input.placeholder = words.title || '';
        input.maxLength = 255;
        input.value = value;

        return [PostFields.fieldLabel(input, words.title || ''), input];
    }

    /** The link box. @returns {[HTMLLabelElement, HTMLInputElement]} */
    static linkField(value = '') {
        const input = document.createElement('input');
        input.type = 'text';
        input.name = 'linkURL';
        const words = Strings.for('PostFields');
        input.placeholder = words.link || '';
        input.maxLength = 255;
        input.value = value;

        return [PostFields.fieldLabel(input, words.link || ''), input];
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
        input.placeholder = Strings.for('PostFields').altText || '';
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
        const words = Strings.for('PostFields');
        input.placeholder = words.contentWarning || '';
        input.maxLength = 255;
        input.value = value;

        return [PostFields.fieldLabel(input, words.contentWarning || ''), input];
    }
}

    return { PostFields };
})();
export const PostFields = PostFieldsModule.PostFields;

// Composer.js
const ComposerModule = (() => {
class Composer extends PostFields {
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

        const words = Strings.for('ComposerClient');
        const legendText = slot === 'reply' ? words.replyLegend || '' : words.createLegend || '';

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
        const words = Strings.for('ComposerClient');
        const poll = document.createElement('fieldset');
        poll.className = 'ComposerPoll';
        poll.style.display = 'none';

        const legend = document.createElement('legend');
        legend.textContent = words.poll || '';
        poll.appendWithSpace(legend);

        for (let index = 0; index < ClientConfig.get('pollMaxOptions'); index++) {
            const option = document.createElement('input');
            option.type = 'text';
            option.className = 'PollOptionInput';
            option.name = 'pollOptions[]';
            option.placeholder = (words.option || '').replace('{count}', String(index + 1));
            poll.appendWithSpace(Composer.fieldLabel(option, (words.pollOption || '').replace('{count}', String(index + 1))));
            poll.appendWithSpace(option);
        }

        const multiple = document.createElement('label');
        multiple.className = 'PollMultipleToggle';

        const multipleInput = document.createElement('input');
        multipleInput.type = 'checkbox';
        multipleInput.name = 'pollMultiple';
        multipleInput.value = '1';
        multiple.appendWithSpace(multipleInput);
        multiple.appendWithSpace(document.createTextNode(words.allowMultiple || ''));

        poll.appendWithSpace(multiple);

        const duration = document.createElement('select');
        duration.className = 'PollDurationSelect';
        duration.name = 'pollDuration';
        poll.appendWithSpace(Composer.fieldLabel(duration, words.pollDuration || ''));

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
        const words = Strings.for('ComposerClient');
        const fieldset = document.createElement('fieldset');
        fieldset.className = 'ComposerFieldset';
        const legend = document.createElement('legend');
        legend.textContent = legendText;
        fieldset.appendWithSpace(legend);

        const titleRow = document.createElement('div');
        titleRow.className = 'PostComposerFields';

        const [titleLabel, titleInput] = Composer.titleField();
        titleRow.appendWithSpace(titleLabel);
        titleRow.appendWithSpace(titleInput);

        const [linkLabel, linkInput] = Composer.linkField();
        titleRow.appendWithSpace(linkLabel);
        titleRow.appendWithSpace(linkInput);

        fieldset.appendWithSpace(titleRow);

        const editorRow = document.createElement('div');
        editorRow.className = 'EditorRow';

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
        editorHelp.textContent = words.richTextHelp || '';
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
        markdownInput.placeholder = words.placeholder || '';
        markdownInput.style.display = 'none';
        editorColumn.appendWithSpace(Composer.fieldLabel(markdownInput, words.markdownPostText || ''));
        editorColumn.appendWithSpace(markdownInput);

        const markdownHelp = document.createElement('p');
        markdownHelp.className = 'ComposerMarkdownHelp visually-hidden';
        markdownHelp.id = 'ComposerMarkdownHelp';
        markdownHelp.textContent = words.plainTextHelp || '';
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

        const markdownBtn = ToggleButton.build([words.useMarkdown || '', words.useRichText || ''], 'MarkdownModeButton');
        actions.appendWithSpace(markdownBtn);

        const removeFilesBtn = document.createElement('button');
        removeFilesBtn.type = 'button';
        removeFilesBtn.className = 'Button ComposerFilesRemoveButton Removing';
        removeFilesBtn.style.display = 'none';
        removeFilesBtn.textContent = words.removeFiles || '';
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
        filePicker.appendWithSpace(document.createTextNode(words.addFiles || ''));

        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.className = 'ComposerFileInput visually-hidden';
        fileInput.multiple = true;
        fileInput.accept = 'image/*,video/*,audio/*';
        // Contains the words on the button, so what is announced and what is
        // read are the same control rather than two names for one thing.
        fileInput.setAttribute('aria-label', words.addFilesLabel || '');
        filePicker.appendWithSpace(fileInput);

        actions.appendWithSpace(filePicker);

        // How much of the post's file allowance is spoken for, said before
        // the cap refuses anybody - empty until something is attached, so a
        // text post never mentions files at all.
        const attachmentCount = document.createElement('span');
        attachmentCount.className = 'ComposerAttachmentCount';
        actions.appendWithSpace(attachmentCount);

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
        sensitiveToggle.appendWithSpace(document.createTextNode(words.sensitive || ''));

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

        const locationButton = ToggleButton.build([words.addLocation || '', words.removeLocation || '', words.locating || ''], 'LocationButton');
        actions.appendWithSpace(locationButton);

        const pollButton = ToggleButton.build([words.addPoll || '', words.removePoll || ''], 'ComposerPollButton');
        actions.appendWithSpace(pollButton);

        // Drafts and scheduling - text/link posts only; #syncFields puts both
        // away when files or a poll are in play, since those publish through
        // paths a StagedPosts row can't carry.
        const scheduleButton = ToggleButton.build([words.addSchedule || '', words.removeSchedule || ''], 'ComposerScheduleButton');
        actions.appendWithSpace(scheduleButton);

        // The clock lives in its own row above the buttons, the way the poll
        // fields do. A date input and a separate, optional time -
        // datetime-local can't say "this day, whenever", and a day alone is
        // a perfectly good schedule (it publishes as the day starts).
        // The component selector supplies the flex layout while the inline
        // display:none keeps the row away until asked for.
        const scheduleRow = document.createElement('div');
        scheduleRow.className = 'ComposerSchedule';
        scheduleRow.style.display = 'none';

        const scheduleDate = document.createElement('input');
        scheduleDate.type = 'date';
        scheduleDate.className = 'ComposerScheduleDate';
        scheduleRow.appendWithSpace(Composer.fieldLabel(scheduleDate, words.publishDate || ''));
        scheduleRow.appendWithSpace(scheduleDate);

        const scheduleTime = document.createElement('input');
        scheduleTime.type = 'time';
        scheduleTime.className = 'ComposerScheduleTime';
        scheduleRow.appendWithSpace(Composer.fieldLabel(scheduleTime, words.publishTime || ''));
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
        draftButton.textContent = words.saveDraft || '';
        commitActions.appendWithSpace(draftButton);

        const submitBtn = ToggleButton.build([words.post || '', words.schedulePost || '', words.saveDraft || ''], 'ComposerSubmitButton', false);
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

        ToggleButton.select(this.submitButton, Strings.for('ComposerClient').saveDraft || '');
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
            const words = Strings.for('ComposerClient');
            ToggleButton.select(this.scheduleButton, hidden ? words.removeSchedule || '' : words.addSchedule || '');
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
            ToggleButton.select(this.submitButton, Strings.for('ComposerClient').saveDraft || '');
            this.submitButton.disabled = !this.#formHasContent()
                || (this.#isScheduling() && !this.#scheduleIsValid());

            return;
        }

        if (this.#isScheduling()) {
            ToggleButton.select(this.submitButton, Strings.for('ComposerClient').schedulePost || '');
            this.submitButton.disabled = !(this.#scheduleIsValid() && this.#formHasContent());
        } else {
            ToggleButton.select(this.submitButton, Strings.for('ComposerClient').post || '');
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
        ToggleButton.select(this.scheduleButton, Strings.for('ComposerClient').addSchedule || '');
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
            Toast.show(Strings.for('ComposerClient').writeFirst || '');
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

            const words = Strings.for('ComposerClient');
            Toast.show(publish_at_epoch === null ? words.savedDraft || '' : words.scheduledDraft || '');

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
        const editor = new QuillEditor(this.editorContainer, { placeholder: Strings.for('ComposerClient').placeholder || '' });
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
                ToggleButton.select(this.pollButton, Strings.for('ComposerClient').removePoll || '');
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
        ToggleButton.select(this.pollButton, Strings.for('ComposerClient').addPoll || '');
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

        // The image arriving beside the editor is silent, and the button that
        // takes it off is nothing anybody would know to look for.
        this.#announce(Strings.for('ComposerClient').linkPreviewAttached || '');
    }

    async #discardStagedImage() {
        if (!this.linkImagePreview) return;
        const seed = this.linkImageSeedInput.value;
        this.linkImageSeedInput.value = '';
        this.linkImagePreview.style.display = 'none';
        this.linkImageThumb.src = '';
        if (seed) {
            this.#announce(Strings.for('ComposerClient').linkPreviewRemoved || '');
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
                    const message = (Strings.for('ComposerClient').tooManyFiles || '').replace('{count}', String(Composer.MAX_FILES));
                    Toast.show(message);
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
            list.className = 'ComposerAttachmentList';
            this.#form.querySelector('.ComposerActions').before(list);
        }

        return list;
    }

    #addAttachment(file) {
        const row = document.createElement('li');
            row.className = 'ComposerAttachment';

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
            name.className = 'ComposerAttachmentName';
        name.textContent = file.name;
        row.appendWithSpace(name);

        if (file.type.startsWith('image/')) {
            const label = (Strings.for('ComposerClient').altTextFor || '').replace('{name}', file.name);
            const [altLabel, altInput] = Composer.altTextField(label);
            entry.altInput = altInput;
            row.appendWithSpace(altLabel);
            row.appendWithSpace(altInput);
        }

        const remove = document.createElement('button');
        remove.type = 'button';
            remove.className = 'Button ComposerAttachmentRemoveButton Removing';
        remove.textContent = Strings.for('ComposerClient').remove || '';
        remove.addEventListener('click', () => this.#removeAttachment(entry));
        row.appendWithSpace(remove);

        this.#attachments.push(entry);
        this.#attachmentList().appendWithSpace(row);
        this.#syncAttachmentCount();

        // A file appearing in a list below is silent, and there is an alt text
        // box that came with it which nobody would know to look for.
        const words = Strings.for('ComposerClient');
        this.#announce(this.#attachments.length === 1
            ? words.oneFileAttached || ''
            : (words.filesAttached || '').replace('{count}', String(this.#attachments.length)));
    }

    #removeAttachment(entry) {
        if (entry.thumbURL !== null) {
            URL.revokeObjectURL(entry.thumbURL);
        }

        entry.row.remove();
        this.#attachments = this.#attachments.filter((candidate) => candidate !== entry);
        this.#syncAttachmentCount();

        const words = Strings.for('ComposerClient');
        this.#announce(this.#attachments.length === 0
            ? words.fileRemovedEmpty || ''
            : (words.filesLeft || '').replace('{count}', String(this.#attachments.length)));

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
            Toast.show(Strings.for('ComposerClient').noGeolocation || '');
            return;
        }

        Working.start(this.locationButton);
        ToggleButton.select(this.locationButton, Strings.for('ComposerClient').locating || '');

        navigator.geolocation.getCurrentPosition(
            (position) => this.#setLocation(position.coords.latitude, position.coords.longitude),
            () => {
                this.#setLocation(null, null);
                Toast.show(Strings.for('ComposerClient').locationError || '');
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    }

    #setLocation(latitude, longitude) {
        const active = latitude !== null && longitude !== null;
        this.latitudeInput.value = active ? latitude : '';
        this.longitudeInput.value = active ? longitude : '';
        Working.stop(this.locationButton);
        const words = Strings.for('ComposerClient');
        ToggleButton.select(this.locationButton, active ? words.removeLocation || '' : words.addLocation || '');
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

    /** The visible tally against the cap - blank until anything is attached. */
    #syncAttachmentCount() {
        const counter = this.#form.querySelector('.ComposerAttachmentCount');

        if (counter) {
            const template = Strings.for('ComposerClient').attachmentCount || '';
            counter.textContent = this.#attachments.length === 0
                ? ''
                : template
                    .replace('{count}', String(this.#attachments.length))
                    .replace('{maximum}', String(Composer.MAX_FILES));
        }
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
        const words = Strings.for('ComposerClient');
        ToggleButton.select(this.markdownButton, this.markdownMode ? words.useRichText || '' : words.useMarkdown || '');

        // Quill's toolbar is its own element beside the editor, and it means
        // nothing while the textarea is the one being written in.
        const toolbar = this.#form.querySelector('.ql-toolbar');

        if (toolbar) {
            toolbar.style.display = this.markdownMode ? 'none' : '';
        }

        this.#announce(this.markdownMode ? words.markdownActive || '' : words.richTextActive || '');

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
                Toast.show(Strings.for('ComposerClient').futurePublish || '');
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
                const msg = getErrorMsg(xhr.responseText) || Strings.for('ComposerClient').submitFailed || '';
                Toast.show(msg);
                return;
            }

            let data;
            try {
                data = JSON.parse(xhr.responseText);
            } catch (error) {
                console.error('Composer: invalid JSON response', xhr.responseText);
                Toast.show(Strings.for('ComposerClient').genericError || '');
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
            Toast.show(Strings.for('ComposerClient').processing || '');
            return;
        }

        const post = Post.fromData(data.response);
        const element = post.toElement();
        RelativeTime.refresh(element);

        if (this.#slot === 'reply') {
            const replyList = document.querySelector('.ReplyList');
            if (replyList) {
                if (!document.querySelector('.RepliesHeading')) {
                    const heading = document.createElement('h2');
                    heading.className = 'RepliesHeading fw-bold text-lg';
                    heading.textContent = Strings.for('ComposerClient').replies || '';
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

    return { Composer };
})();
export const Composer = ComposerModule.Composer;

// PostEditor.js
const PostEditorModule = (() => {
class PostEditor extends PostFields {
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
        RelativeTime.refresh(newContent);
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
        import('/scripts/HTMLObjects.js').then(({ EmojiRenderer }) => {
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

    return { PostEditor };
})();
export const PostEditor = PostEditorModule.PostEditor;

// AccountDeleteForm.js
const AccountDeleteFormModule = (() => {
/**
 * Closing your own account, which asks for the password first.
 *
 * Api is handed the form, so a wrong password is said under the password box
 * rather than as a loose line above the button.
 */
class AccountDeleteForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.AccountDeleteForm');
            if (!form) return;
            event.preventDefault();

            if (!await Dialog.confirm(Strings.for('ClientStatus').deleteAccount || '')) return;

            const submit_button = form.querySelector('button[type="submit"]');
            Working.start(submit_button);

            try {
                const data = await Api.post('/api/delete-account', {
                    currentPassword: form.querySelector('[name="currentPassword"]').value,
                }, { form });

                if (!data) return;

                window.location = ClientConfig.siteURL() + '/';
            } finally {
                Working.stop(submit_button);
            }
        });
    }
}

ReadyHandler.add(AccountDeleteForm.init);

    return { AccountDeleteForm };
})();
export const AccountDeleteForm = AccountDeleteFormModule.AccountDeleteForm;

// AccountMigrationForm.js
const AccountMigrationFormModule = (() => {
/**
 * Declaring an alias, and moving away.
 *
 * The two are saved together but mean different things: an alias is only a
 * permission for another account to move here, while a destination actually
 * sends the move. The endpoint saves the aliases first for that reason, so a
 * refused move does not also lose them.
 */
class AccountMigrationForm {
    static init() {
        const form = document.querySelector('.AccountMigrationForm');

        if (!form) return;

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const movedTo = form.querySelector('[name="movedTo"]').value.trim();
            const alsoKnownAs = form.querySelector('[name="alsoKnownAs"]').value;

            if (movedTo !== '' && !await confirmMove(movedTo)) {
                return;
            }

            const submit = form.querySelector('button[type="submit"]');
            Working.start(submit);

            try {
                const result = await Api.post('/api/account-migration', { movedTo, alsoKnownAs });

                if (!result) return;

                const words = Strings.for('ClientStatus');
                Toast.show(result.moved ? words.followersNotified || '' : words.saved || '');
            } finally {
                Working.stop(submit);
            }
        });
    }
}

async function confirmMove(destination) {
    const { Dialog } = await import('/scripts/HTMLObjects.js');

    return Dialog.confirm(
        `Move this account to ${destination}? Your followers will be asked to follow you there, and your posts stay here - they cannot be taken along.`
    );
}

ReadyHandler.add(AccountMigrationForm.init);

    return { AccountMigrationForm };
})();
export const AccountMigrationForm = AccountMigrationFormModule.AccountMigrationForm;

// AvatarUploadForm.js
const AvatarUploadFormModule = (() => {
class AvatarUploadForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.AvatarUploadForm');
            if (!form) return;
            event.preventDefault();

            const submit_button = form.querySelector('button[type="submit"]');
            Working.start(submit_button);

            try {
                const data = await Api.post('/api/upload-avatar', new FormData(form), { form });

                if (!data) return;

                const avatar = document.createElement('img');
                avatar.className = 'Avatar';
                avatar.alt = Strings.for('ClientStatus').avatarAlt || '';
                avatar.src = data.image;
                form.closest('.User').querySelector('.UserLink .Avatar').replaceWith(avatar);
            } finally {
                Working.stop(submit_button);
            }
        });
    }
}

ReadyHandler.add(AvatarUploadForm.init);

    return { AvatarUploadForm };
})();
export const AvatarUploadForm = AvatarUploadFormModule.AvatarUploadForm;

// BlockedServerCard.js
const BlockedServerCardModule = (() => {
/**
 * The moderation page for shutting out whole servers: the form that adds one
 * and the control on each row that lifts it.
 *
 * Blocking is confirmed rather than immediate, because it is not a small act -
 * it severs every follow in both directions with that server, and lifting the
 * block afterwards does not bring them back.
 */
class BlockedServerCard {
    static init() {
        const form = document.querySelector('.ServerBlockForm');

        if (form) {
            form.addEventListener('submit', (event) => {
                event.preventDefault();
                BlockedServerCard.#block(form);
            });
        }

        document.addEventListener('click', (event) => {
            const button = event.target.closest('.ServerUnblockButton');

            if (button) {
                BlockedServerCard.#unblock(button);
            }
        });
    }

    static async #block(form) {
        const domain = form.querySelector('[name="domain"]').value.trim();
        const reason = form.querySelector('[name="reason"]').value.trim();

        if (domain === '') return;

        const confirmed = await Dialog.confirm(
            `Block ${domain}? Nothing will be sent to or accepted from that server or anything under it, and existing follows in both directions will be dropped. Unblocking later does not restore them.`
        );

        if (!confirmed) return;

        const submit = form.querySelector('button[type="submit"]');
        Working.start(submit);

        try {
            const result = await Api.post('/api/block-server', { domain, reason });

            if (!result) return;

            // The new row joins the list in place - the same card the server
            // renders for one. The cascade the confirmation warned about
            // (severed follows, dropped deliveries) has no rendering on this
            // page, so the list is the whole picture here.
            const list = list_in(document.querySelector('.BlockedServersSetting'), 'BlockedServerList');

            if (list) {
                list.prepend(list_item(BlockedServerCard.#card(result.domain, reason)));
            }

            form.querySelector('[name="domain"]').value = '';
            form.querySelector('[name="reason"]').value = '';
        } finally {
            Working.stop(submit);
        }
    }

    static #card(domain, reason) {
        const card = document.createElement('div');
        card.className = 'BlockedServerCard';
        card.dataset.domain = domain;

        const info = document.createElement('div');
        info.className = 'BlockedServerCardInfo';

        const name = document.createElement('p');
        name.textContent = domain;
        info.appendWithSpace(name);

        // Mirrors BlockedServerCard.php: the time is its own element between
        // two text nodes, so a language can put it anywhere in the sentence.
        const words = Strings.for('BlockedServerCard', {
            blockedBy: { before: 'Blocked by {name} ', after: '' },
            deletedAccount: 'a deleted account',
            reason: ' - {reason}',
        });

        const detail = document.createElement('p');
        detail.className = 'BlockedServerCardDetail';
        detail.appendWithSpace(document.createTextNode(
            (words.blockedBy.before || '').replace('{name}', ClientConfig.get('currentUserUsername') || '')
        ));

        const time = document.createElement('time');
        time.className = 'RelativeTime';
        time.dateTime = new Date().toISOString();
        time.textContent = RelativeTime.format(time.dateTime);
        detail.appendWithSpace(time);
        detail.appendWithSpace(document.createTextNode(words.blockedBy.after || ''));

        if (reason !== '') {
            detail.appendWithSpace(document.createTextNode(words.reason.replace('{reason}', reason)));
        }

        info.appendWithSpace(detail);
        card.appendWithSpace(info);

        const unblock = document.createElement('button');
        unblock.type = 'button';
        unblock.className = 'Button ServerUnblockButton';
        unblock.dataset.domain = domain;
        unblock.textContent = Strings.for('ServerUnblockButton', { name: 'Unblock' }).name;
        card.appendWithSpace(unblock);

        return card;
    }

    static async #unblock(button) {
        const domain = button.dataset.domain;

        if (!await Dialog.confirm(`Unblock ${domain}? Follows that were dropped are not restored - both sides would have to follow again.`)) {
            return;
        }

        Working.start(button);

        try {
            const result = await Api.post('/api/unblock-server', { domain });

            if (!result) return;

            Toast.show(`${domain} unblocked.`);
            DOMUtils.slideOut(button.closest('.BlockedServerCard'));
        } finally {
            Working.stop(button);
        }
    }
}

ReadyHandler.add(BlockedServerCard.init);

    return { BlockedServerCard };
})();
export const BlockedServerCard = BlockedServerCardModule.BlockedServerCard;

// BotProtectionSettingsForm.js
const BotProtectionSettingsFormModule = (() => {
class BotProtectionSettingsForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.BotProtectionSettingsForm');
            if (!form) return;
            event.preventDefault();
            const submit_button = form.querySelector('button[type="submit"]');
            Working.start(submit_button);
            const data = await Api.post('/api/turnstile-settings', {
                turnstileSiteKey: form.querySelector('[name="turnstileSiteKey"]').value,
                turnstileSecretKey: form.querySelector('[name="turnstileSecretKey"]').value,
                recaptchaSiteKey: form.querySelector('[name="recaptchaSiteKey"]').value,
                recaptchaSecretKey: form.querySelector('[name="recaptchaSecretKey"]').value,
            });
            Working.stop(submit_button);
            if (data) Toast.show(Strings.for('ClientStatus').settingsSaved || '');
        });
    }
}

ReadyHandler.add(BotProtectionSettingsForm.init);

    return { BotProtectionSettingsForm };
})();
export const BotProtectionSettingsForm = BotProtectionSettingsFormModule.BotProtectionSettingsForm;

// CarouselController.js
const CarouselControllerModule = (() => {
class CarouselController {
    static AUTOPLAY_IMAGE_DELAY = 3000;

    constructor() {
        this._autoplayMap = new WeakMap();
        // Carousels whose current media autoplay started playing itself, so the
        // resulting 'play' event isn't mistaken for the viewer taking over and
        // stopping autoplay. The distinction is this controller's own state, so
        // it lives here beside the autoplay map rather than on the element.
        this._autoplayStartedPlay = new WeakSet();
        this._fullscreenState = null;

        this._offScreenObserver = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) return;
                    if (entry.target.matches('video, audio')) {
                        if (!entry.target.paused) entry.target.pause();
                    } else {
                        this._stopAutoplay(entry.target);
                    }
                });
            },
            { rootMargin: '50% 0px' }
        );

        this._onClick = this._onClick.bind(this);
        this._onMediaPlay = this._onMediaPlay.bind(this);
        this._onMediaPause = this._onMediaPause.bind(this);
        this._onMediaEnded = this._onMediaEnded.bind(this);
    }

    init() {
        document.addEventListener('click', this._onClick);
        document.addEventListener('play', this._onMediaPlay, true);
        document.addEventListener('pause', this._onMediaPause, true);
        document.addEventListener('ended', this._onMediaEnded, true);

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && this._fullscreenState) {
                this._exitFullscreen();
            }
        });

        this._observeOffScreen(document.body);

        new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === 1) this._observeOffScreen(node);
                });
                mutation.removedNodes.forEach((node) => {
                    if (node.nodeType === 1) this._unobserveOffScreen(node);
                });
            });
        }).observe(document.body, { childList: true, subtree: true });
    }

    _loadSlide(slide) {
        if (!slide) return;
        slide.querySelectorAll('[data-src]').forEach((media) => {
            if (media.dataset.poster) {
                media.poster = media.dataset.poster;
                delete media.dataset.poster;
            }
            media.src = media.dataset.src;
            delete media.dataset.src;

            if (media.closest('.MediaFullscreenOverlay') && media instanceof HTMLImageElement && media.dataset.fullSrc) {
                media.src = media.dataset.fullSrc;
                delete media.dataset.fullSrc;
            }
        });
    }

    _advance(carousel, direction) {
        const slides = Array.from(carousel.querySelectorAll('.CarouselSlide'));
        const currentIndex = slides.findIndex((slide) => slide.classList.contains('Active'));

        // A carousel always arrives with one slide showing. If it somehow does
        // not, findIndex says -1 and the arithmetic below lands on the last
        // slide - which is a stranger place to start than the first, and the
        // line after would throw and take every later click with it.
        if (slides.length === 0 || currentIndex === -1) {
            slides[0]?.classList.add('Active');

            return;
        }

        const nextIndex = (currentIndex + direction + slides.length) % slides.length;

        slides[currentIndex].classList.remove('Active');
        slides[nextIndex].classList.add('Active');

        carousel.querySelectorAll('video, audio').forEach((media) => media.pause());

        this._loadSlide(slides[nextIndex]);

        for (let i = nextIndex + 1; i <= nextIndex + ClientConfig.get('carouselEagerItems') && i < slides.length; i++) {
            this._loadSlide(slides[i]);
        }

        const counter = carousel.querySelector('.CarouselCounter');
        if (counter) counter.textContent = (nextIndex + 1) + ' / ' + slides.length;
    }

    _scheduleAutoplayAdvance(carousel) {
        if (!this._autoplayMap.has(carousel)) return;

        const media = carousel.querySelector('.CarouselSlide.Active video, .CarouselSlide.Active audio');
        if (media) {
            this._autoplayMap.set(carousel, null);
            this._autoplayStartedPlay.add(carousel);
            media.play().catch(() => {
                this._autoplayStartedPlay.delete(carousel);
            });
            return;
        }

        const timeoutId = setTimeout(() => {
            this._advance(carousel, 1);
            this._scheduleAutoplayAdvance(carousel);
        }, CarouselController.AUTOPLAY_IMAGE_DELAY);

        this._autoplayMap.set(carousel, timeoutId);
    }

    _startAutoplay(carousel) {
        if (this._autoplayMap.has(carousel)) return;
        this._autoplayMap.set(carousel, null);
        this._scheduleAutoplayAdvance(carousel);
        const toggle = carousel.querySelector('.CarouselAutoplayButton');
        if (toggle) toggle.textContent = Strings.for('CarouselController').stopAutoplay || '';
    }

    _stopAutoplay(carousel) {
        if (!this._autoplayMap.has(carousel)) return;
        const pendingTimeout = this._autoplayMap.get(carousel);
        if (pendingTimeout) clearTimeout(pendingTimeout);
        this._autoplayMap.delete(carousel);
        const toggle = carousel.querySelector('.CarouselAutoplayButton');
        if (toggle) toggle.textContent = Strings.for('CarouselController').autoplay || '';
    }

    _enterFullscreen(container) {
        if (this._fullscreenState) return;

        const originalParent = container.parentNode;
        const originalNextSibling = container.nextSibling;

        const overlay = document.createElement('div');
        overlay.className = 'MediaFullscreenOverlay';
        document.body.appendWithSpace(overlay);
        overlay.appendWithSpace(container);
        container.classList.add('InFullscreen');

        container.querySelectorAll('img[data-full-src]').forEach(img => {
            if (img.src !== img.dataset.fullSrc) {
                img.src = img.dataset.fullSrc;
                img.removeAttribute('data-full-src');
            }
        });

        const button = container.querySelector(':scope > .MediaFullscreenButton');
        if (button) {
            button.textContent = '×';
            button.setAttribute('aria-label', Strings.for('CarouselController').exitFullscreen || '');
        }

        this._fullscreenState = { container, overlay, originalParent, originalNextSibling };
    }

    _exitFullscreen() {
        if (!this._fullscreenState) return;
        const { container, overlay, originalParent, originalNextSibling } = this._fullscreenState;
        container.classList.remove('InFullscreen');
        originalParent.insertBefore(container, originalNextSibling);
        overlay.remove();
        const button = container.querySelector(':scope > .MediaFullscreenButton');
        if (button) {
            button.textContent = '⛶';
            button.setAttribute('aria-label', Strings.for('CarouselController').fullscreen || '');
        }
        this._fullscreenState = null;
    }

    _observeOffScreen(root) {
        if (root.matches?.('video, audio, .Carousel')) {
            this._offScreenObserver.observe(root);
        }
        root.querySelectorAll?.('video, audio, .Carousel').forEach((el) =>
            this._offScreenObserver.observe(el)
        );
    }

    _unobserveOffScreen(root) {
        if (root.matches?.('video, audio, .Carousel')) {
            this._offScreenObserver.unobserve(root);
        }
        root.querySelectorAll?.('video, audio, .Carousel').forEach((el) =>
            this._offScreenObserver.unobserve(el)
        );
    }

    _onClick(event) {
        const prevNext = event.target.closest('.CarouselPrevButton, .CarouselNextButton');
        if (prevNext) {
            const carousel = prevNext.closest('.Carousel');
            this._stopAutoplay(carousel);
            this._advance(carousel, prevNext.classList.contains('CarouselNextButton') ? 1 : -1);
            return;
        }

        const autoplayBtn = event.target.closest('.CarouselAutoplayButton');
        if (autoplayBtn) {
            const carousel = autoplayBtn.closest('.Carousel');
            if (this._autoplayMap.has(carousel)) {
                this._stopAutoplay(carousel);
            } else {
                this._startAutoplay(carousel);
            }
            return;
        }

        const img = event.target.closest('.Carousel .ImageItem img, .FeedItem .ImageItem img');
        if (img) {
            const carousel = img.closest('.Carousel');
            this._stopAutoplay(carousel);

            if (!this._fullscreenState) {
                const container = img.closest('.Carousel, .FeedItem');
                if (container) this._enterFullscreen(container);
            }

            return;
        }

        const fullscreenBtn = event.target.closest('.MediaFullscreenButton');
        if (fullscreenBtn) {
            if (this._fullscreenState) {
                this._exitFullscreen();
            } else {
                const container = fullscreenBtn.closest('.Carousel, .FeedItem');
                if (container) this._enterFullscreen(container);
            }
            return;
        }
    }

    _onMediaPlay(event) {
        const media = event.target.closest('.Carousel video, .Carousel audio');
        if (!media) return;
        const carousel = media.closest('.Carousel');
        if (this._autoplayStartedPlay.has(carousel)) {
            this._autoplayStartedPlay.delete(carousel);
            return;
        }
        this._stopAutoplay(carousel);
    }

    _onMediaPause(event) {
        const media = event.target.closest?.('.Carousel video, .Carousel audio');
        if (!media || media.ended) return;
        this._stopAutoplay(media.closest('.Carousel'));
    }

    _onMediaEnded(event) {
        const media = event.target.closest('.Carousel video, .Carousel audio');
        if (!media) return;
        const carousel = media.closest('.Carousel');
        if (!this._autoplayMap.has(carousel)) return;
        this._advance(carousel, 1);
        this._scheduleAutoplayAdvance(carousel);
    }
}

    return { CarouselController };
})();
export const CarouselController = CarouselControllerModule.CarouselController;

// EmailChangeForm.js
const EmailChangeFormModule = (() => {
/**
 * Changing the address the site writes to, which asks for the password too.
 *
 * Api is handed the form, so "that address is already in use" lands on the
 * address box and a wrong password lands on the password box - both at once
 * where both are wrong.
 */
class EmailChangeForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.EmailChangeForm');
            if (!form) return;
            event.preventDefault();

            const submit_button = form.querySelector('button[type="submit"]');
            Working.start(submit_button);

            try {
                const data = await Api.post('/api/change-email', {
                    newEmail: form.querySelector('[name="newEmail"]').value,
                    currentPassword: form.querySelector('[name="currentPassword"]').value,
                }, { form });

                if (!data) return;

                if (!data.changed) {
                    Toast.show(Strings.for('ClientStatus').emailUnchanged || '');
                    return;
                }

                window.location = ClientConfig.siteURL() + '/check-inbox';
            } finally {
                Working.stop(submit_button);
            }
        });
    }
}

ReadyHandler.add(EmailChangeForm.init);

    return { EmailChangeForm };
})();
export const EmailChangeForm = EmailChangeFormModule.EmailChangeForm;

// EmailDigestSetting.js
const EmailDigestSettingModule = (() => {
/**
 * Client twin of EmailDigestSetting.php: saves the answer as it is given.
 *
 * The box is left showing what they chose even while the request is in flight -
 * a checkbox that springs back and then settles reads as a fault. If the save
 * fails, Api has already told them so.
 */
class EmailDigestSetting {
    static init() {
        document.addEventListener('change', async (event) => {
            const input = event.target.closest('.EmailDigestSetting input[name="emailDigests"]');

            if (!input) return;

            await Api.post('/api/update-email-digests', { emailDigests: input.checked });
        });
    }
}

ReadyHandler.add(EmailDigestSetting.init);

    return { EmailDigestSetting };
})();
export const EmailDigestSetting = EmailDigestSettingModule.EmailDigestSetting;

// EmailDigestSettingsForm.js
const EmailDigestSettingsFormModule = (() => {
/**
 * Client twin of EmailDigestSettingsForm.php: saves the paragraph this server
 * adds to every digest.
 */
class EmailDigestSettingsForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.EmailDigestSettingsForm');
            if (!form) return;
            event.preventDefault();
            const submit_button = form.querySelector('button[type="submit"]');
            Working.start(submit_button);
            const field = form.querySelector('textarea');
            const data = await Api.post('/api/email-digest-settings', { [field.name]: field.value });
            Working.stop(submit_button);
            if (data) Toast.show(Strings.for('ClientStatus').settingsSaved || '');
        });
    }
}

ReadyHandler.add(EmailDigestSettingsForm.init);

    return { EmailDigestSettingsForm };
})();
export const EmailDigestSettingsForm = EmailDigestSettingsFormModule.EmailDigestSettingsForm;

// EmojiPicker.js
const EmojiPickerModule = (() => {
let activePanel = null;
let activeWrapper = null;

// Global click‑outside listener – closes the panel when clicking elsewhere
document.addEventListener('click', (event) => {
    if (!activePanel) return;
    if (event.target.closest('.EmojiPickerTriggerButton')) return;
    if (event.target.closest('emoji-picker')) return;
    closeActive();
});

function closeActive() {
    if (activePanel && activeWrapper) {
        activePanel.classList.remove('Active');
        // Move panel back to its original wrapper
        activeWrapper.appendChild(activePanel);
        activePanel = null;
        activeWrapper = null;
    }
}

class EmojiPicker {
    static initAll(root = document) {
        root.querySelectorAll('.EmojiPicker').forEach(btn => EmojiPicker.setup(btn));
    }

    static setup(wrapper) {
        const trigger = wrapper.querySelector('.EmojiPickerTriggerButton');
        const panel  = wrapper.querySelector('emoji-picker');
        if (!trigger || !panel) return;

        // Replace trigger to remove any previous event listeners
        const newTrigger = trigger.cloneNode(true);
        trigger.replaceWith(newTrigger);

        newTrigger.addEventListener('click', (e) => {
            e.stopPropagation();

            // If this panel is already active, close it
            if (panel === activePanel) {
                closeActive();
                return;
            }

            // Close any other open panel
            closeActive();

            // Move the panel to document.body so fixed positioning is relative
            // to the viewport (avoids issues when the button is inside a fixed/transformed container)
            document.body.appendChild(panel);

            // Shown before it is placed, so it can be measured rather than
            // assumed: the picker decides its own size, and a number written
            // down here would only ever be the wrong one by a few pixels.
            // Hidden across the measurement so it is never painted at the
            // default position first.
            panel.style.visibility = 'hidden';
            panel.style.position = 'fixed';
            panel.style.top = '0';
            panel.style.left = '0';
            panel.classList.add('Active');

            const triggerRect = newTrigger.getBoundingClientRect();
            const panelRect = panel.getBoundingClientRect();
            const gap = 4;

            // Vertical: prefer below the trigger
            let top = triggerRect.bottom + gap;
            if (top + panelRect.height > window.innerHeight) {
                top = triggerRect.top - panelRect.height - gap;
            }
            if (top < gap) top = gap;

            // Horizontal: align left edges, keep inside viewport
            let left = triggerRect.left;
            if (left + panelRect.width > window.innerWidth) {
                left = window.innerWidth - panelRect.width - gap;
            }
            if (left < gap) left = gap;

            panel.style.top  = top + 'px';
            panel.style.left = left + 'px';
            panel.style.visibility = '';

            activePanel = panel;
            activeWrapper = wrapper;
        });

        // Emoji insertion
        panel.addEventListener('emoji-click', (event) => {
            const emoji = event.detail.unicode;
            const form = wrapper.closest('form');
            const quill = form?.querySelector('.QuillEditor')?.__quill;
            if (quill) {
                const selection = quill.getSelection(true);
                quill.insertText(selection.index, emoji, 'user');
                quill.setSelection(selection.index + emoji.length, 0, 'user');
                return;
            }
            const textarea = form?.querySelector('textarea');
            if (textarea) {
                const start = textarea.selectionStart;
                const end = textarea.selectionEnd;
                const value = textarea.value;
                textarea.value = value.slice(0, start) + emoji + value.slice(end);
                textarea.selectionStart = textarea.selectionEnd = start + emoji.length;
                textarea.focus();
            }
        });

        // Skin tone preference
        panel.addEventListener('skin-tone-change', async (event) => {
            await Api.post('/api/update-skin-tone', { skinTone: String(event.detail.skinTone) });
        });
    }
}

    return { EmojiPicker };
})();
export const EmojiPicker = EmojiPickerModule.EmojiPicker;

// EncryptedMessagesSetting.js
const EncryptedMessagesSettingModule = (() => {
/**
 * The Settings section for encrypted messaging. Key generation, wrapping,
 * and rewrapping all happen here in the browser; the server only ever
 * receives the public key and the passphrase-wrapped private key. Turning it
 * on rebuilds the section in place to the same DOM the server renders for an
 * enabled account - no page reload.
 */
class EncryptedMessagesSetting {
    static MIN_PASSPHRASE_LENGTH = 12;

    /**
     * Keys created or rewrapped since the page loaded. Preferred over what the
     * section was rendered with, which only knows what existed at that moment
     * - without this, a passphrase change right after enabling (or a second
     * change in a row) would unwrap a stale blob.
     */
    static #keys = null;

    /** The keys the section was rendered with (EncryptedMessagesSetting.php). */
    static #storedKeys(form) {
        const section = form.closest('.EncryptedMessagesSetting');

        return {
            publicKey: JSON.parse(section.dataset.publicKey),
            wrappedPrivateKey: JSON.parse(section.dataset.wrappedPrivateKey),
        };
    }

    static init() {
        document.addEventListener('submit', (event) => {
            const setup_form = event.target.closest('.MessageKeySetupForm');
            if (setup_form) {
                event.preventDefault();
                EncryptedMessagesSetting.#setup(setup_form);
                return;
            }

            const passphrase_form = event.target.closest('.MessageKeyPassphraseForm');
            if (passphrase_form) {
                event.preventDefault();
                EncryptedMessagesSetting.#changePassphrase(passphrase_form);
            }
        });
    }

    /** Creates a keypair (or replaces one - the reset variant) and stores it wrapped. */
    static async #setup(form) {
        const passphrase = form.querySelector('[name="passphrase"]').value;
        const account_password = form.querySelector('[name="setupAccountPassword"]').value;

        if (!EncryptedMessagesSetting.#acceptable(passphrase, form.querySelector('[name="passphraseConfirm"]').value, account_password)) return;

        const submit_button = form.querySelector('button[type="submit"]');
        Working.start(submit_button);

        try {
            const pair = await MessageCrypto.generateKeypair();
            const wrapped = await MessageCrypto.wrapPrivateKey(pair.privateKey, passphrase);

            const result = await Api.post('/api/message-keys', {
                publicKey: pair.publicKey,
                wrappedPrivateKey: wrapped,
                password: account_password,
            }, { form });
            if (result === null) return;

            EncryptedMessagesSetting.#keys = { publicKey: pair.publicKey, wrappedPrivateKey: wrapped };

            // The tab that just made the key is already unlocked with it.
            MessageCrypto.storeUnlocked(pair.privateKey);

            // The same form serves first-time setup and the reset an enabled
            // account offers; only the former changes the section's shape.
            if (form.closest('.EncryptedMessagesSetting').querySelector('.MessageKeyPassphraseForm') !== null) {
                Toast.show(Strings.for('EncryptedMessagesClient').newKeys || '');
                form.reset();
            } else {
                EncryptedMessagesSetting.#showEnabled(form);
                Toast.show(Strings.for('EncryptedMessagesClient').enabled || '');
            }
        } finally {
            Working.stop(submit_button);
        }
    }

    /** Same key, new wrapping: unwrap under the old passphrase, rewrap under the new. */
    static async #changePassphrase(form) {
        const new_passphrase = form.querySelector('[name="newPassphrase"]').value;
        const account_password = form.querySelector('[name="rewrapAccountPassword"]').value;

        if (!EncryptedMessagesSetting.#acceptable(new_passphrase, form.querySelector('[name="newPassphraseConfirm"]').value, account_password)) return;

        const keys = EncryptedMessagesSetting.#keys ?? EncryptedMessagesSetting.#storedKeys(form);
        const private_jwk = await MessageCrypto.unwrapPrivateKey(keys.wrappedPrivateKey, form.querySelector('[name="currentPassphrase"]').value);

        if (private_jwk === null) {
            Toast.show(Strings.for('EncryptedMessagesClient').wrongPassphrase || '');
            return;
        }

        const submit_button = form.querySelector('button[type="submit"]');
        Working.start(submit_button);

        try {
            const wrapped = await MessageCrypto.wrapPrivateKey(private_jwk, new_passphrase);

            const result = await Api.post('/api/message-keys', {
                publicKey: keys.publicKey,
                wrappedPrivateKey: wrapped,
                password: account_password,
            }, { form });
            if (result === null) return;

            EncryptedMessagesSetting.#keys = { publicKey: keys.publicKey, wrappedPrivateKey: wrapped };

            Toast.show(Strings.for('EncryptedMessagesClient').passphraseChanged || '');
            form.reset();
        } finally {
            Working.stop(submit_button);
        }
    }

    /**
     * Swaps the section from its setup state to the enabled one the server
     * renders: a status line, the change-passphrase form, and the reset form
     * (see EncryptedMessagesSetting.php).
     */
    static #showEnabled(setup_form) {
        const section = setup_form.closest('.EncryptedMessagesSetting');
        setup_form.remove();

        // Same keys MessageKeySetupForm.php reads, so the rebuilt reset form
        // says what the server would have said for the same element.
        const setup_words = Strings.for('MessageKeySetupForm');
        const passphrase_words = Strings.for('MessageKeyPassphraseForm');

        const status = document.createElement('p');
        status.textContent = Strings.for('EncryptedMessagesSetting').enabledStatus || '';
        section.appendWithSpace(status);

        // MessageKeyPassphraseForm's own labels - not sourced from Strings
        // here because that class isn't converted, so there is nothing yet
        // to read them from.
        const passphrase_form = document.createElement('form');
        passphrase_form.className = 'Form MessageKeyPassphraseForm';
        passphrase_form.appendWithSpace(input_field('currentPassphrase', passphrase_words.currentPassphraseLabel || '', 'current-password'));
        passphrase_form.appendWithSpace(input_field('newPassphrase', passphrase_words.newPassphraseLabel || '', 'new-password'));
        passphrase_form.appendWithSpace(input_field('newPassphraseConfirm', passphrase_words.confirmNewPassphraseLabel || '', 'new-password'));
        passphrase_form.appendWithSpace(input_field('rewrapAccountPassword', passphrase_words.accountPasswordLabel || '', 'current-password'));
        passphrase_form.appendWithSpace(submit_button(passphrase_words.submit || ''));
        section.appendWithSpace(passphrase_form);

        const reset_form = document.createElement('form');
        reset_form.className = 'Form MessageKeySetupForm';

        const warning = document.createElement('p');
        warning.textContent = setup_words.resetWarning;
        reset_form.appendWithSpace(warning);

        reset_form.appendWithSpace(input_field('passphrase', setup_words.resetPassphraseLabel, 'new-password'));
        reset_form.appendWithSpace(input_field('passphraseConfirm', setup_words.confirmLabel, 'new-password'));
        reset_form.appendWithSpace(input_field('setupAccountPassword', setup_words.accountPasswordLabel, 'current-password'));
        reset_form.appendWithSpace(submit_button(setup_words.resetSubmitLabel));
        section.appendWithSpace(reset_form);
    }

    /**
     * What is wrong with a proposed passphrase, or null if nothing is.
     *
     * This one secret guards every encrypted message the account will ever
     * hold, on every device, for as long as the account exists - there is no
     * second factor behind it and no reset that keeps the messages. So the bar
     * is higher than a password's, where a lockout and a reset stand behind a
     * weak choice.
     *
     * Reusing the account password is refused outright and is the important
     * one: the account password is sent to the server to authorise this very
     * change, so making it the passphrase too hands the server the key it is
     * not supposed to have, and the encryption stops meaning anything.
     */
    static passphraseProblem(passphrase, confirmation, account_password) {
        const words = Strings.for('EncryptedMessagesClient');
        if (passphrase.length < EncryptedMessagesSetting.MIN_PASSPHRASE_LENGTH) {
            return (words.minimumPassphrase || '').replace('{count}', String(EncryptedMessagesSetting.MIN_PASSPHRASE_LENGTH));
        }

        if (passphrase !== confirmation) {
            return words.passphrasesMismatch || '';
        }

        if (account_password !== '' && passphrase === account_password) {
            return words.passphraseIsPassword || '';
        }

        if (new Set(passphrase).size < 5) {
            return words.passphraseTooRepetitive || '';
        }

        return null;
    }

    static #acceptable(passphrase, confirmation, account_password) {
        const problem = EncryptedMessagesSetting.passphraseProblem(passphrase, confirmation, account_password);

        if (problem !== null) {
            Toast.show(problem);

            return false;
        }

        return true;
    }
}

function input_field(name, label, autocomplete) {
    const field = document.createElement('div');
    field.className = 'InputField';

    const label_element = document.createElement('label');
    label_element.htmlFor = name;
    label_element.textContent = label;
    field.appendWithSpace(label_element);

    const input = document.createElement('input');
    input.type = 'password';
    input.name = name;
    input.id = name;
    input.placeholder = label;
    input.setAttribute('autocomplete', autocomplete);
    field.appendWithSpace(input);

    return field;
}

function submit_button(label) {
    const button = document.createElement('button');
    button.type = 'submit';
    button.className = 'Button SubmitButton';
    button.textContent = label;

    return button;
}

ReadyHandler.add(EncryptedMessagesSetting.init);

    return { EncryptedMessagesSetting };
})();
export const EncryptedMessagesSetting = EncryptedMessagesSettingModule.EncryptedMessagesSetting;

// EntityModerator.js
const EntityModeratorModule = (() => {
class EntityModerator {
    static init() {
        document.addEventListener('click', async (event) => {
            const banBtn = event.target.closest('.TrendingEntityBanButton');
            if (banBtn) {
                EntityModerator.#ban(banBtn);
                return;
            }

            const unbanBtn = event.target.closest('.TrendingEntityUnbanButton');
            if (unbanBtn) {
                EntityModerator.#unban(unbanBtn);
            }
        });
    }

    static async #ban(button) {
        const entityType = button.dataset.entityType;
        const entityValue = button.dataset.entityValue;
        const reason = await Dialog.prompt(
            `Ban "${entityValue}" from trending? It won't be able to trend again until unbanned.`,
            {
                confirmLabel: Strings.for('EntityModerator').ban || '',
                placeholder: Strings.for('EntityModerator').banPlaceholder || '',
            }
        );
        if (reason === null) return;
        Working.start(button);
        try {
            const result = await Api.post('/api/ban-trending-entity', { entityType, entityValue, reason });
            if (!result) return;
            DOMUtils.slideOut(button.closest('.Entity'));
        } finally {
            Working.stop(button);
        }
    }

    static async #unban(button) {
        const entityType = button.dataset.entityType;
        const entityValue = button.dataset.entityValue;
        const message = (Strings.for('EntityModerator').unban || '').replace('{entity}', entityValue);
        if (!await Dialog.confirm(message)) return;
        Working.start(button);
        try {
            const result = await Api.post('/api/unban-trending-entity', { entityType, entityValue });
            if (!result) return;
            DOMUtils.slideOut(button.closest('.BannedTrendingEntity'));
        } finally {
            Working.stop(button);
        }
    }
}

ReadyHandler.add(EntityModerator.init);

    return { EntityModerator };
})();
export const EntityModerator = EntityModeratorModule.EntityModerator;

// FaviconSettingsForm.js
const FaviconSettingsFormModule = (() => {
class FaviconSettingsForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.FaviconSettingsForm');
            if (!form) return;
            event.preventDefault();

            const file_input = form.querySelector('input[type="file"][name="favicon"]');

            if (!file_input.files.length) {
                Toast.show(Strings.for('ClientStatus').chooseFile || '');

                return;
            }

            const submit_button = form.querySelector('button[type="submit"]');
            Working.start(submit_button);

            const body = new FormData();
            body.append('favicon', file_input.files[0]);

            try {
                const data = await Api.post('/api/favicon-settings', body, { form });

                if (!data) return;

                Toast.show(Strings.for('ClientStatus').faviconSaved || '');
                form.querySelector('.FaviconPreview').src = ClientConfig.siteURL() + '/uploads/site/favicon.png?' + Date.now();
            } finally {
                Working.stop(submit_button);
            }
        });
    }
}

ReadyHandler.add(FaviconSettingsForm.init);

    return { FaviconSettingsForm };
})();
export const FaviconSettingsForm = FaviconSettingsFormModule.FaviconSettingsForm;

// FrontPageImageSettingsForm.js
const FrontPageImageSettingsFormModule = (() => {
class FrontPageImageSettingsForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.FrontPageImageSettingsForm');
            if (!form) return;
            event.preventDefault();

            const file_input = form.querySelector('input[type="file"][name="frontPageImage"]');

            if (!file_input.files.length) {
                Toast.show(Strings.for('ClientStatus').chooseFile || '');

                return;
            }

            const submit_button = form.querySelector('button[type="submit"]');
            Working.start(submit_button);

            const body = new FormData();
            body.append('frontPageImage', file_input.files[0]);

            try {
                const data = await Api.post('/api/front-page-image', body, { form });

                if (!data) return;

                Toast.show(Strings.for('ClientStatus').settingsSaved || '');

                // First upload has no preview element yet; a reload-free page
                // gets one the next time the form renders, and the cache-bust
                // keeps an existing one honest.
                const preview = form.querySelector('.FrontPageImagePreview');

                if (preview) {
                    preview.src = data.url + '?' + Date.now();
                }
            } finally {
                Working.stop(submit_button);
            }
        });
    }
}

ReadyHandler.add(FrontPageImageSettingsForm.init);

    return { FrontPageImageSettingsForm };
})();
export const FrontPageImageSettingsForm = FrontPageImageSettingsFormModule.FrontPageImageSettingsForm;

// GoogleAuthSettingsForm.js
const GoogleAuthSettingsFormModule = (() => {
class GoogleAuthSettingsForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.GoogleAuthSettingsForm');
            if (!form) return;
            event.preventDefault();
            const submit_button = form.querySelector('button[type="submit"]');
            Working.start(submit_button);
            const data = await Api.post('/api/google-auth-settings', {
                googleAuthClientId: form.querySelector('[name="googleAuthClientId"]').value,
                googleAuthSecret: form.querySelector('[name="googleAuthSecret"]').value,
            });
            Working.stop(submit_button);
            if (data) Toast.show(Strings.for('ClientStatus').settingsSaved || '');
        });
    }
}

ReadyHandler.add(GoogleAuthSettingsForm.init);

    return { GoogleAuthSettingsForm };
})();
export const GoogleAuthSettingsForm = GoogleAuthSettingsFormModule.GoogleAuthSettingsForm;

// HashtagGraphList.js
const HashtagGraphListModule = (() => {
const GRAPH_SELECTOR = '.HashtagGraphList';
const GRAPH_BREAKPOINT = '(min-width: 48rem)';

/**
 * The /tags/ 3D force-directed hashtag graph (mirrors HashtagGraphList.php).
 *
 * Two decoupled layers, per the classic split: a force simulation lays the tags
 * out in 3D MODEL space (repulsion between all nodes, attraction along the
 * co-occurrence edges - tighter the more posts two tags share, gravity to keep
 * it centred), and a separate view QUATERNION rotates that settled structure as
 * a rigid body for drawing. The physics never fights a drag because its forces
 * only depend on distances, which rotation leaves unchanged.
 *
 * The layout is settled once, synchronously, on load, then the settled
 * structure drifts in a slow constant auto-spin about the vertical screen axis.
 * A drag rotates it directly and pauses the spin, which resumes when released -
 * no inertia, no fling, just the steady drift and the direct drag.
 *
 * Nodes are the server-rendered HashtagNode links (still clickable); edges are
 * drawn on a <canvas> underlay (a rendering surface, not app "things", so no
 * per-line DOM). Sizes come from each tag's post count.
 *
 * Progressive enhancement: the server renders the tags as a plain
 * HashtagGraphList (see HashtagGraphList.php), which this upgrades in place to the graph only at
 * or above the layout breakpoint. Below it the list is left alone - the graph
 * captures touch and wheel to rotate and zoom, which on a phone would trap the
 * page's own scroll.
 */

// --- quaternion helpers (x, y, z, w) ---------------------------------------

function quat_multiply(a, b) {
    const ax = a[0], ay = a[1], az = a[2], aw = a[3];
    const bx = b[0], by = b[1], bz = b[2], bw = b[3];

    return [
        aw * bx + ax * bw + ay * bz - az * by,
        aw * by - ax * bz + ay * bw + az * bx,
        aw * bz + ax * by - ay * bx + az * bw,
        aw * bw - ax * bx - ay * by - az * bz,
    ];
}

function quat_normalize(q) {
    const length = Math.hypot(q[0], q[1], q[2], q[3]) || 1;

    return [q[0] / length, q[1] / length, q[2] / length, q[3] / length];
}

// Row-major 3x3 rotation matrix for a unit quaternion.
function quat_to_matrix(q) {
    const x = q[0], y = q[1], z = q[2], w = q[3];
    const xx = x * x, yy = y * y, zz = z * z;
    const xy = x * y, xz = x * z, yz = y * z;
    const wx = w * x, wy = w * y, wz = w * z;

    return [
        1 - 2 * (yy + zz), 2 * (xy - wz), 2 * (xz + wy),
        2 * (xy + wz), 1 - 2 * (xx + zz), 2 * (yz - wx),
        2 * (xz - wy), 2 * (yz + wx), 1 - 2 * (xx + yy),
    ];
}

class HashtagGraphList {
    // Layout / physics tuning.
    static MAX_ITERATIONS = 320;
    static GRAVITY = 0.03;
    static COOL = 0.986;

    // Interaction.
    static RADIANS_PER_PIXEL = 0.006;
    static DRAG_THRESHOLD = 5;

    // Idle auto-spin: a full turn takes ~80s. Very slow on purpose.
    static SPIN_RADIANS_PER_SECOND = 0.08;

    // The body font size (rem); node sizes are expressed as multiples of it.
    static BASE_FONT_REM = 0.9375;
    // Default layout spread, as a multiple of the viewport half-size - >1 so the
    // graph is bigger than the viewport (nodes spaced out, not a cramped blob);
    // the wheel zooms from there.
    static SPREAD = 1.5;
    static MIN_ZOOM = 0.2;
    static MAX_ZOOM = 12;
    static WHEEL_STEP = 1.12;

    constructor(element) {
        this.element = element;
        this.nodeElements = Array.from(element.querySelectorAll('.HashtagNode'));
        this.count = this.nodeElements.length;

        let edges = [];

        try {
            const section = element.closest('.HashtagGraphList');
            edges = JSON.parse((section && section.dataset.edges) || '[]');
        } catch (error) {
            edges = [];
        }

        // View rotation, zoom, and the current drag.
        this.orientation = [0, 0, 0, 1];
        this.zoom = HashtagGraphList.MAX_ZOOM / 2; // start at 6 (previous max)
        this.dragging = false;
        this.suppressClick = false;

        this.sizeNodes();
        element.classList.add('Active');
        // Force reflow to ensure the CSS height is applied before measuring
        void element.offsetHeight;

        this.canvas = document.createElement('canvas');
        this.canvas.className = 'HashtagGraphEdges';
        this.canvas.setAttribute('aria-hidden', 'true');
        this.context = this.canvas.getContext('2d');
        element.insertBefore(this.canvas, element.firstChild);

        this.buildEdges(edges);
        this.measure();
        this.seed();
        // Settle the whole layout up front (a few hundred cheap iterations), so
        // it appears already laid out and dead still - it only ever moves when
        // dragged, never on its own.
        this.settle();
        this.render();
        this.startSpin();

        this.boundResize = () => this.onResize();
        window.addEventListener('resize', this.boundResize);
    }

    // Stops the spin loop and drops the resize listener - otherwise both would
    // keep running (and keep the detached element/canvas alive) forever if the
    // graph's section is ever removed from the document.
    destroy() {
        window.removeEventListener('resize', this.boundResize);
    }

    // The idle drift: a constant angular velocity about the vertical screen axis
    // (a Y-axis delta applied in view space, same side as a drag's), rendered
    // per animation frame. Paused while dragging - the elapsed time still
    // advances, so releasing resumes the drift without a jump. Frame-rate
    // independent (scaled by the real elapsed time), and no inertia: the
    // velocity is fixed, never accumulated from the drag.
    startSpin() {
        let last = null;

        const frame = (now) => {
            // The graph never gets torn down explicitly today, but if its
            // section is ever removed from the document this stops the loop
            // instead of spinning a detached graph forever.
            if (!this.element.isConnected) {
                this.destroy();
                return;
            }

            if (last !== null && !this.dragging) {
                const angle = (now - last) / 1000 * HashtagGraphList.SPIN_RADIANS_PER_SECOND;
                const spin = [0, Math.sin(angle / 2), 0, Math.cos(angle / 2)];
                this.orientation = quat_normalize(quat_multiply(spin, this.orientation));
                this.render();
            }

            last = now;
            requestAnimationFrame(frame);
        };

        requestAnimationFrame(frame);
    }

    // Font-size (and so node size) scales with the log of the post count, so one
    // runaway tag can't dwarf the rest - spread from half the base body font
    // (the least-used tag) up to 3.75x it (the most-used).
    sizeNodes() {
        const logs = this.nodeElements.map((node) => Math.log(1 + Number(node.dataset.count || 1)));
        const min = Math.min(...logs);
        const max = Math.max(...logs);
        const span = max - min || 1;

        this.nodeElements.forEach((node, index) => {
            const normalized = (logs[index] - min) / span;
            node.style.fontSize = (HashtagGraphList.BASE_FONT_REM * (0.5 + normalized * 3.25)).toFixed(3) + 'rem';
            node.draggable = false;
            node.style.position = 'absolute';
        });
    }

    buildEdges(edges) {
        const weights = edges.map((edge) => edge.weight);
        const max_log = Math.log(1 + Math.max(1, ...weights));

        this.edges = edges
            .filter((edge) => edge.a < this.count && edge.b < this.count)
            .map((edge) => {
                const strength = Math.log(1 + edge.weight) / (max_log || 1);

                return {
                    a: edge.a,
                    b: edge.b,
                    // More shared posts -> stronger pull.
                    attraction: 1 + strength * 3,
                    lineWidth: 0.6 + strength * 2.2,
                };
            });
    }

    measure() {
        const rect = this.element.getBoundingClientRect();
        this.width = rect.width;
        this.height = rect.height;
        this.radius = Math.max(60, Math.min(this.width, this.height) * 0.34);
        this.ideal = 1.1 * this.radius / Math.cbrt(Math.max(2, this.count));

        // Node collision radius from its rendered box.
        this.nodeRadius = this.nodeElements.map((node) => Math.max(node.offsetWidth, node.offsetHeight) / 2 + 2);

        const ratio = window.devicePixelRatio || 1;
        this.canvas.width = Math.round(this.width * ratio);
        this.canvas.height = Math.round(this.height * ratio);
        this.context.setTransform(ratio, 0, 0, ratio, 0, 0);

        const edge_color = getComputedStyle(this.element).getPropertyValue('--HashtagEdge').trim();
        this.edgeColor = edge_color || 'rgba(120, 130, 125, 0.5)';

        // Keep the layout scaled to the (possibly resized) box.
        if (this.maxExtent) {
            this.computeScale();
        }
    }

    // Even, deterministic starting spread on a sphere (a Fibonacci lattice) -
    // never all at the origin, which would divide by zero into NaN.
    seed() {
        const count = this.count;
        this.position = new Float64Array(count * 3);
        this.displacement = new Float64Array(count * 3);
        const golden = Math.PI * (3 - Math.sqrt(5));
        const start = this.radius * 0.5;

        for (let i = 0; i < count; i++) {
            const y = count === 1 ? 0 : 1 - (i / (count - 1)) * 2;
            const ring = Math.sqrt(Math.max(0, 1 - y * y));
            const theta = golden * i;
            this.position[i * 3] = Math.cos(theta) * ring * start;
            this.position[i * 3 + 1] = y * start;
            this.position[i * 3 + 2] = Math.sin(theta) * ring * start;
        }

        this.temperature = this.radius * 0.16;
    }

    // Run the whole simulation to rest right now (cheap at these sizes), then
    // work out the base scale.
    settle() {
        for (let i = 0; i < HashtagGraphList.MAX_ITERATIONS; i++) {
            this.stepPhysics();
        }

        this.computeScale();
    }

    // Scale the settled layout by its furthest node so, at zoom 1, the graph
    // spans SPREAD times the viewport half-size - i.e. spills beyond the viewport
    // so the nodes are spaced out rather than piled together. The wheel scales
    // this.zoom on top.
    computeScale() {
        let max_squared = 1;

        for (let i = 0; i < this.count; i++) {
            const x = this.position[i * 3];
            const y = this.position[i * 3 + 1];
            const z = this.position[i * 3 + 2];
            const squared = x * x + y * y + z * z;

            if (squared > max_squared) {
                max_squared = squared;
            }
        }

        this.maxExtent = Math.sqrt(max_squared);
        this.baseScale = (Math.min(this.width, this.height) * 0.5 * HashtagGraphList.SPREAD) / this.maxExtent;
    }

    onWheel(deltaY) {
        const step = deltaY < 0 ? HashtagGraphList.WHEEL_STEP : 1 / HashtagGraphList.WHEEL_STEP;
        this.zoom = Math.max(HashtagGraphList.MIN_ZOOM, Math.min(HashtagGraphList.MAX_ZOOM, this.zoom * step));
        this.render();
    }

    // One Fruchterman-Reingold-style step: repel every pair, attract along edges,
    // pull toward the centre, then move each node by at most the current
    // "temperature" (which cools each step) so the layout can never explode.
    stepPhysics() {
        const position = this.position;
        const displacement = this.displacement;
        const count = this.count;
        const k = this.ideal;
        displacement.fill(0);

        for (let i = 0; i < count; i++) {
            for (let j = i + 1; j < count; j++) {
                const dx = position[i * 3] - position[j * 3];
                const dy = position[i * 3 + 1] - position[j * 3 + 1];
                const dz = position[i * 3 + 2] - position[j * 3 + 2];
                const distance = Math.sqrt(dx * dx + dy * dy + dz * dz) || 0.01;

                let force = (k * k) / distance;

                // Keep big nodes from overlapping their neighbours.
                const minimum = this.nodeRadius[i] + this.nodeRadius[j] + 4;
                if (distance < minimum) {
                    force += (minimum - distance) * 0.9;
                }

                const fx = (dx / distance) * force;
                const fy = (dy / distance) * force;
                const fz = (dz / distance) * force;

                displacement[i * 3] += fx;
                displacement[i * 3 + 1] += fy;
                displacement[i * 3 + 2] += fz;
                displacement[j * 3] -= fx;
                displacement[j * 3 + 1] -= fy;
                displacement[j * 3 + 2] -= fz;
            }
        }

        for (const edge of this.edges) {
            const a = edge.a, b = edge.b;
            const dx = position[a * 3] - position[b * 3];
            const dy = position[a * 3 + 1] - position[b * 3 + 1];
            const dz = position[a * 3 + 2] - position[b * 3 + 2];
            const distance = Math.sqrt(dx * dx + dy * dy + dz * dz) || 0.01;

            const force = ((distance * distance) / k) * edge.attraction;
            const fx = (dx / distance) * force;
            const fy = (dy / distance) * force;
            const fz = (dz / distance) * force;

            displacement[a * 3] -= fx;
            displacement[a * 3 + 1] -= fy;
            displacement[a * 3 + 2] -= fz;
            displacement[b * 3] += fx;
            displacement[b * 3 + 1] += fy;
            displacement[b * 3 + 2] += fz;
        }

        const temperature = this.temperature;
        let center_x = 0, center_y = 0, center_z = 0;

        for (let i = 0; i < count; i++) {
            displacement[i * 3] -= position[i * 3] * HashtagGraphList.GRAVITY;
            displacement[i * 3 + 1] -= position[i * 3 + 1] * HashtagGraphList.GRAVITY;
            displacement[i * 3 + 2] -= position[i * 3 + 2] * HashtagGraphList.GRAVITY;

            const dx = displacement[i * 3];
            const dy = displacement[i * 3 + 1];
            const dz = displacement[i * 3 + 2];
            const length = Math.sqrt(dx * dx + dy * dy + dz * dz) || 0.01;
            const limited = Math.min(length, temperature) / length;

            position[i * 3] += dx * limited;
            position[i * 3 + 1] += dy * limited;
            position[i * 3 + 2] += dz * limited;

            center_x += position[i * 3];
            center_y += position[i * 3 + 1];
            center_z += position[i * 3 + 2];
        }

        // Pin the centroid at the origin so the ball spins in place, not orbits.
        center_x /= count;
        center_y /= count;
        center_z /= count;

        for (let i = 0; i < count; i++) {
            position[i * 3] -= center_x;
            position[i * 3 + 1] -= center_y;
            position[i * 3 + 2] -= center_z;
        }

        this.temperature = Math.max(this.radius * 0.006, temperature * HashtagGraphList.COOL);
    }

    // Turn a screen-space drag delta into an incremental rotation and premultiply
    // it onto the orientation - premultiplying keeps it screen-relative, so
    // "drag right" always spins right no matter how the graph is already turned.
    applyScreenDelta(dx, dy) {
        const distance = Math.hypot(dx, dy);
        if (distance < 1e-4) {
            return;
        }

        const angle = distance * HashtagGraphList.RADIANS_PER_PIXEL;
        const scale = Math.sin(angle / 2) / distance;
        const delta = [-dy * scale, dx * scale, 0, Math.cos(angle / 2)];

        this.orientation = quat_normalize(quat_multiply(delta, this.orientation));
    }

    render() {
        const matrix = quat_to_matrix(this.orientation);
        const center_x = this.width / 2;
        const center_y = this.height / 2;
        const extent = this.maxExtent || 1;
        const fit = (this.baseScale || 1) * this.zoom;
        const position = this.position;
        const projected = [];

        for (let i = 0; i < this.count; i++) {
            const x = position[i * 3], y = position[i * 3 + 1], z = position[i * 3 + 2];
            const rx = (matrix[0] * x + matrix[1] * y + matrix[2] * z) * fit;
            const ry = (matrix[3] * x + matrix[4] * y + matrix[5] * z) * fit;
            const rz = matrix[6] * x + matrix[7] * y + matrix[8] * z;

            const depth = Math.max(0, Math.min(1, (rz + extent) / (2 * extent)));
            const scale = 0.62 + depth * 0.58;

            projected.push({ x: center_x + rx, y: center_y + ry, depth });

            const node = this.nodeElements[i];
            node.style.transform =
                'translate(-50%, -50%) translate3d(' + (center_x + rx).toFixed(1) + 'px, ' + (center_y + ry).toFixed(1) + 'px, 0) scale(' + scale.toFixed(3) + ')';
            node.style.opacity = (0.4 + depth * 0.6).toFixed(3);
            node.style.zIndex = String(Math.round(depth * 100));
        }

        this.drawEdges(projected);
    }

    drawEdges(projected) {
        const context = this.context;
        context.clearRect(0, 0, this.width, this.height);

        for (const edge of this.edges) {
            const a = projected[edge.a];
            const b = projected[edge.b];
            const depth = (a.depth + b.depth) / 2;

            context.globalAlpha = 0.12 + depth * 0.5;
            context.strokeStyle = this.edgeColor;
            context.lineWidth = edge.lineWidth * (0.7 + depth * 0.6);
            context.beginPath();
            context.moveTo(a.x, a.y);
            context.lineTo(b.x, b.y);
            context.stroke();
        }

        context.globalAlpha = 1;
    }

    onResize() {
        this.measure();
        this.render();
    }

    // --- drag handling (driven by the delegated document listeners) ---------

    onDown(event) {
        this.dragging = true;
        this.suppressClick = false;
        this.startX = event.clientX;
        this.startY = event.clientY;
        this.lastX = event.clientX;
        this.lastY = event.clientY;
        this.moved = false;
    }

    onMove(event) {
        if (!this.dragging) {
            return;
        }

        const dx = event.clientX - this.lastX;
        const dy = event.clientY - this.lastY;
        this.lastX = event.clientX;
        this.lastY = event.clientY;

        if (!this.moved && Math.hypot(event.clientX - this.startX, event.clientY - this.startY) > HashtagGraphList.DRAG_THRESHOLD) {
            this.moved = true;
        }

        if (this.moved) {
            this.suppressClick = true;
            this.applyScreenDelta(dx, dy);
            this.render();
        }
    }

    onUp() {
        this.dragging = false;
    }
}

// --- delegated interaction --------------------------------------------------

function graph_for(target) {
    const element = target.closest(GRAPH_SELECTOR + '.Active');
    return element && element.__hashtagGraphList ? element.__hashtagGraphList : null;
}

let active_graph = null;

document.addEventListener('pointerdown', (event) => {
    const graph = graph_for(event.target);
    if (!graph) {
        return;
    }

    // Text selection and link-image drag are already blocked in CSS, so we don't
    // preventDefault here - doing so (or capturing the pointer) would stop a
    // plain tag click from navigating to its /tags/ page.
    active_graph = graph;
    graph.onDown(event);
});

// Wheel zooms the graph (and only the graph - the page mustn't scroll).
document.addEventListener('wheel', (event) => {
    const graph = graph_for(event.target);
    if (!graph) {
        return;
    }

    event.preventDefault();
    graph.onWheel(event.deltaY);
}, { passive: false });

document.addEventListener('pointermove', (event) => {
    if (active_graph) {
        active_graph.onMove(event);
    }
});

function end_drag(event) {
    if (active_graph) {
        active_graph.onUp(event);
        active_graph = null;
    }
}

document.addEventListener('pointerup', end_drag);
document.addEventListener('pointercancel', end_drag);

// A drag that started on a tag must not also follow its link on release.
document.addEventListener('click', (event) => {
    const node = event.target.closest('.HashtagNode');
    if (!node) {
        return;
    }

    const element = node.closest(GRAPH_SELECTOR);
    if (element && element.__hashtagGraphList && element.__hashtagGraphList.suppressClick) {
        event.preventDefault();
        element.__hashtagGraphList.suppressClick = false;
    }
});

function init_tag_graphs(root) {
    // Below the breakpoint the tags stay a plain, scrollable list - building the
    // graph there would capture the touch/wheel the page needs to scroll.
    if (!window.matchMedia(GRAPH_BREAKPOINT).matches) {
        return;
    }

    (root || document).querySelectorAll(GRAPH_SELECTOR).forEach((element) => {
        if (!element.__hashtagGraphList) {
            element.__hashtagGraphList = new HashtagGraphList(element);
        }
    });
}

// Run after the DOM is ready (module scripts are deferred, so the DOM is
// usually already available).
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => init_tag_graphs());
} else {
    init_tag_graphs();
}

    return { HashtagGraphList };
})();
export const HashtagGraphList = HashtagGraphListModule.HashtagGraphList;

// HelpSearch.js
const HelpSearchModule = (() => {
/**
 * The client render of Help results - the twin of HelpCategory and
 * HelpArticleSummary, rebuilding the same cards from /api/help-search's JSON.
 * The searching itself is driven by the Search section below, the same machinery as every
 * other search box on the site; this module only knows how the results look.
 */
class HelpSearch {
    /** The browse view: every article grouped under its category heading. */
    static renderBrowse(results, articles) {
        let current_category = null;
        let current_list = null;

        articles.forEach((article) => {
            if (article.category !== current_category) {
                current_category = article.category;
                const section = HelpSearch.categoryElement(current_category);
                current_list = section.querySelector('.HelpArticleList');
                results.appendWithSpace(section);
            }

            current_list.appendWithSpace(HelpSearch.articleSummaryElement(article));
        });
    }

    /** A flat ranked list, for a typed query. */
    static renderResults(results, articles) {
        const list = document.createElement('div');
        list.className = 'HelpArticleList';
        articles.forEach((article) => list.appendWithSpace(HelpSearch.articleSummaryElement(article)));
        results.appendWithSpace(list);
    }

    // Mirror of HelpArticleSummary::toDOM() - the whole card is a link.
    static articleSummaryElement(article) {
        const card = document.createElement('a');
        card.className = 'HelpArticleSummary';
        card.href = article.url;

        const title = document.createElement('h3');
        title.textContent = article.title;
        card.appendWithSpace(title);

        const summary = document.createElement('p');
        summary.className = 'HelpArticleSummaryText';
        summary.textContent = article.summary;
        card.appendWithSpace(summary);

        return card;
    }

    // Mirror of HelpCategory::toDOM().
    static categoryElement(name) {
        const section = document.createElement('section');
        section.className = 'HelpCategory';

        const heading = document.createElement('h2');
        heading.textContent = name;
        section.appendWithSpace(heading);

        const list = document.createElement('div');
        list.className = 'HelpArticleList';
        section.appendWithSpace(list);

        return section;
    }
}

    return { HelpSearch };
})();
export const HelpSearch = HelpSearchModule.HelpSearch;

// InfiniteScroller.js
const InfiniteScrollerModule = (() => {
const REGISTRY = {};

class InfiniteScroller {
    static register(type, renderItem, countOffset) {
        REGISTRY[type] = { renderItem, countOffset };
    }

    static init() {
        document.querySelectorAll('[data-infinite-scroll]').forEach(el => {
            new InfiniteScroller(el);
        });
    }

    static create(list, overrides) {
        return new InfiniteScroller(list, overrides);
    }

    #list;
    #loading = false;

    /** The live region that says what arrived, made on first use. */
    #announcer = null;

    /** The last payload, so the announcement can name what it held. */
    #lastResponse = null;
    #active = true;
    static #THRESHOLD = 150;

    /**
     * How long the view has to be still before its position is acted on. Short
     * enough that flicking to the end of a feed still loads the moment it
     * lands, long enough that the whole of a smooth scroll counts as one
     * arrival rather than a hundred.
     */
    static #SETTLE_MS = 120;

    #scrollTimer;
    #onScroll;
    #onResize;

    constructor(list, overrides) {
        this.#list = list;

        let endpoint, direction, buildReq, renderItem, countOffset, wrapper;
        let resolveEndpoint = null;

        if (overrides) {
            if (typeof overrides.endpoint === 'function') {
                resolveEndpoint = overrides.endpoint;
                endpoint = resolveEndpoint();
            } else {
                endpoint = overrides.endpoint;
            }
            direction   = overrides.direction ?? 'down';
            renderItem  = overrides.renderItem;
            countOffset = overrides.countOffset;
            buildReq    = overrides.buildRequest || (offset => ({ offset }));
            wrapper     = overrides.wrapper ?? list_item;

            if (overrides.active === false) {
                this.#active = false;
            }
        } else {
            const config = JSON.parse(list.dataset.infiniteScroll);
            const type = config.itemType;
            const entry = REGISTRY[type];
            if (!entry) throw new Error(`InfiniteScroller: unknown item type "${type}"`);

            endpoint  = config.endpoint;
            direction = config.direction ?? 'down';

            // Everything the list's own config names is part of the request,
            // so a feed selected by more than its type (one profile's posts,
            // one tag's) pages correctly with no per-type special casing here.
            const extraFields = { ...config };
            delete extraFields.endpoint;
            delete extraFields.itemType;
            delete extraFields.direction;
            delete extraFields.cursor;

            this._cursor = config.cursor ?? null;
            buildReq = offset => ({
                ...extraFields,
                offset,
                ...(this._cursor ? { cursor: this._cursor } : {}),
            });

            renderItem  = entry.renderItem;
            countOffset = entry.countOffset;
            wrapper     = list_item;
        }

        this._endpoint        = endpoint;
        this._resolveEndpoint = resolveEndpoint;
        this._direction       = direction;
        this._buildReq        = buildReq;
        this._renderItem      = renderItem;
        this._countOffset     = countOffset;
        this._wrapper         = wrapper;

        // Debounced, because a smooth scroll is not one scroll event but a
        // stream of them all the way down. Reacting to each would load a page
        // of messages for every frame of the journey; waiting until the view
        // has come to rest asks once, about where it actually stopped.
        this.#onScroll = () => {
            clearTimeout(this.#scrollTimer);
            this.#scrollTimer = setTimeout(() => this.#handleScroll(), InfiniteScroller.#SETTLE_MS);
        };

        this.#onResize = () => this.#fill();

        window.addEventListener('scroll', this.#onScroll, { passive: true });
        window.addEventListener('resize', this.#onResize, { passive: true });

        this.#fill();
    }

    setActive(active) {
        this.#active = active;

        if (active) this.#fill();
    }

    destroy() {
        this.#active = false;
        clearTimeout(this.#scrollTimer);

        if (this.#onScroll) {
            window.removeEventListener('scroll', this.#onScroll);
            this.#onScroll = null;
        }

        if (this.#onResize) {
            window.removeEventListener('resize', this.#onResize);
            this.#onResize = null;
        }
    }

    #nearEdge() {
        if (this._direction === 'up') return window.scrollY <= InfiniteScroller.#THRESHOLD;
        return window.innerHeight + window.scrollY >= document.body.scrollHeight - InfiniteScroller.#THRESHOLD;
    }

    #scrollable() {
        return document.body.scrollHeight > window.innerHeight;
    }

    /**
     * Keeps asking for pages until the page is long enough to scroll, because
     * a page that fits the window never fires a scroll event: the reader is
     * left with one page and no way to ask for the next.
     *
     * The stop is a page that made the document no taller, not a try count. A
     * page that added no height will not add any next time, so nothing spins;
     * a count would still spend its whole budget on every short list.
     */
    async #fill() {
        while (this.#active && !this.#scrollable()) {
            const height = document.body.scrollHeight;

            await this.#load();

            if (document.body.scrollHeight <= height) return;
        }
    }

    async #handleScroll() {
        if (this.#loading) return;
        if (!this.#list || !this.#active) return;
        if (!this.#nearEdge()) return;

        await this.#load();
    }

    async #load() {
        if (this.#loading) return;
        if (!this.#list || !this.#active) return;

        this.#loading = true;

        const spinner = document.createElement('li');
        spinner.className = 'LoadingSpinner';
        spinner.setAttribute('aria-label', Strings.for('InfiniteScroller').loading || '');

        if (this._direction === 'up') {
            this.#list.insertBeforeWithSpace(spinner, this.#list.firstChild);
        } else {
            this.#list.appendWithSpace(spinner);
        }

        try {
            const url = this._resolveEndpoint ? this._resolveEndpoint() : this._endpoint;
            const offset = this._countOffset(this.#list);

            // request() rather than post(), because what to do next turns on
            // the status: it says nothing itself, and the wording below is the
            // scroller's own.
            const result = await Api.request(url, this._buildReq(offset));

            // A refused page won't start working on the next scroll event, so
            // stop asking and say so - silently returning leaves the reader
            // looking at what appears to be the end of the feed while every
            // further scroll re-sends the same doomed request. Being throttled
            // is the exception: that one really is worth retrying.
            if (!result.ok) {
                if (result.status !== 429) {
                    this.#active = false;
                    Toast.show(Strings.for('InfiniteScroller').failed || '');
                }

                return;
            }

            const { hasMore, items } = this.#extractItems(result.data);

            if (!hasMore) {
                this.#active = false;
            }

            if (items?.length) {
                if (this._direction === 'up') {
                    const prevH = document.body.scrollHeight;
                    const prevY = window.scrollY;

                    for (const item of items) {
                        const el = this._renderItem(item);
                        RelativeTime.refresh(el);
                        this.#list.insertBeforeWithSpace(this._wrapper(el), spinner);
                        render_math(el);
                    }

                    const newH = document.body.scrollHeight;
                    window.scrollTo({ top: prevY + (newH - prevH), behavior: 'instant' });
                } else {
                    for (const item of items) {
                        const el = this._renderItem(item);
                        RelativeTime.refresh(el);
                        this.#list.insertBeforeWithSpace(this._wrapper(el), spinner);
                        render_math(el);
                    }
                }
            }
            if (items?.length) {
                this.#announce(items.length + ' more ' + this.#noun(items.length) + ' loaded.');
            } else if (!hasMore) {
                this.#announce(Strings.for('InfiniteScroller').end || '');
            }
        } catch (e) {
            console.error('InfiniteScroller error:', e);
        } finally {
            spinner.remove();
            this.#loading = false;
        }
    }

    /** What this list is a list of, for the announcement. */
    #noun(count) {
        const resp = this.#lastResponse || {};

        for (const [key, word] of [
            ['posts', 'post'], ['messages', 'message'], ['notifications', 'notification'],
            ['reports', 'report'], ['users', 'person'],
        ]) {
            if (Array.isArray(resp[key])) {
                return count === 1 ? word : (word === 'person' ? 'people' : word + 's');
            }
        }

        return count === 1 ? 'item' : 'items';
    }

    /**
     * Says what just arrived, for somebody who cannot see it arrive.
     *
     * A feed that grows as you scroll is silent: more posts appear below and
     * nothing tells a screen reader they are there, so the reader has no
     * reason to go looking. Polite, because this is never urgent - it waits
     * for whatever is being read to finish.
     */
    #announce(text) {
        if (!this.#announcer) {
            this.#announcer = document.createElement('div');
            this.#announcer.className = 'visually-hidden';
            this.#announcer.setAttribute('role', 'status');
            this.#announcer.setAttribute('aria-live', 'polite');
            this.#list.parentNode?.insertBefore(this.#announcer, this.#list.nextSibling);
        }

        this.#announcer.textContent = text;
    }

    #extractItems(data) {
        const resp = data.response || data;
        this.#lastResponse = resp;

        if (Object.hasOwn(resp, 'cursor')) {
            this._cursor = resp.cursor;
        }

        const items = resp.items || resp.posts || resp.messages ||
                      resp.notifications || resp.reports || resp.users || [];
        return { hasMore: resp.hasMore, items };
    }
}

// ----------------------------------------------------------------
// Centralised type registrations
// ----------------------------------------------------------------

InfiniteScroller.register('Post',
    data => Post.fromData(data).toElement(),
    list => list.querySelectorAll('.Post').length
);

InfiniteScroller.register('Message',
    data => Message.fromData(data).toElement(),
    list => list.querySelectorAll('.Message').length
);

InfiniteScroller.register('OtherUser',
    data => OtherUser.fromData(data).toElement(),
    list => list.querySelectorAll('.OtherUser').length
);

InfiniteScroller.register('ReceivedFriendRequest',
    data => ReceivedFriendRequest.fromData(data).toElement(),
    list => list.querySelectorAll('.OtherUser').length
);

InfiniteScroller.register('Notification',
    data => Notification.fromData(data).toElement(),
    list => list.querySelectorAll('.Notification').length
);

InfiniteScroller.register('Report',
    data => Report.fromData(data).toElement(),
    list => list.querySelectorAll('.Report').length
);

InfiniteScroller.register('Entity',
    data => Entity.fromData(data).toElement(),
    list => list.querySelectorAll('.Entity').length
);

ReadyHandler.add(InfiniteScroller.init);

    return { InfiniteScroller };
})();
export const InfiniteScroller = InfiniteScrollerModule.InfiniteScroller;

// LanguagePrompt.js
const LanguagePromptModule = (() => {
/**
 * Choosing which language to read the site in - from the offer made to
 * somebody whose browser is set to one this site speaks, and from the selector
 * in their settings.
 *
 * Both do the same thing, because they are the same decision: tell the server,
 * then load the page again so it arrives in the language just chosen. A page
 * already built cannot be re-translated in place - every string on it came
 * from the server - so the reload is the point rather than a shortcut.
 *
 * The offer is asked as a real dialog, which is what makes it answerable by
 * somebody on a keyboard: it holds focus, Escape answers it, and the page
 * behind it is not still tabbable underneath. The server renders the words -
 * they are in a language this page is not in, so they cannot come from the
 * strings the browser loaded - and this hands them to the dialog.
 *
 * Declining is an answer too. It is recorded the same way, so the offer is
 * made once and not on every page after.
 */
class LanguagePrompt {
    static init() {
        document.addEventListener('change', async (event) => {
            const select = event.target.closest('.LanguageSelect');

            if (select) {
                await LanguagePrompt.choose(select.value);
            }
        });

        const offer = document.querySelector('.LanguagePrompt');

        if (offer) {
            LanguagePrompt.ask(offer);
        }
    }

    static async ask(offer) {
        const locale = offer.dataset.locale;
        const words = (selector) => offer.querySelector(selector)?.textContent ?? '';

        const question = words('.LanguagePromptQuestion');
        const accept = words('.LanguagePromptAccept');
        const decline = words('.LanguagePromptDecline');

        offer.remove();

        if (await Dialog.confirm(question, { confirmText: accept, cancelText: decline })) {
            await LanguagePrompt.choose(locale);

            return;
        }

        // Staying put changes nothing on the page, so nothing is fetched again -
        // but it is still an answer, and recording it is what stops the asking.
        await LanguagePrompt.remember(document.documentElement.lang);
    }

    /** Tells the server, and says whether it took. */
    static async remember(locale) {
        if (!locale) {
            return false;
        }

        return await Api.post('/api/set-language', { locale }) !== null;
    }

    static async choose(locale) {
        if (await LanguagePrompt.remember(locale)) {
            window.location.reload();
        }
    }
}

ReadyHandler.add(LanguagePrompt.init);

    return { LanguagePrompt };
})();
export const LanguagePrompt = LanguagePromptModule.LanguagePrompt;

// LoginForm.js
const LoginFormModule = (() => {
class LoginForm {
    static #recaptchaLoading = null;

    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.LoginForm');
            if (!form) return;
            event.preventDefault();

            const submit_button = form.querySelector('button[type="submit"]');
            Working.start(submit_button);

            const captcha_input = form.querySelector('[name="cf-turnstile-response"]');
            const recaptcha_token = form.recaptchaWidgetId !== undefined && window.grecaptcha
                ? window.grecaptcha.getResponse(form.recaptchaWidgetId)
                : null;

            const data = await Api.post('/api/login', {
                identifier: form.querySelector('[name="identifier"]').value,
                password: form.querySelector('[name="password"]').value,
                rememberMe: form.querySelector('[name="rememberMe"]').checked,
                captchaToken: captcha_input ? captcha_input.value : null,
                recaptchaToken: recaptcha_token || null,
            }, { form });

            if (!data) {
                LoginForm.#resetRecaptcha(form);
                Working.stop(submit_button);
                return;
            }

            if (data.recaptchaRequired) {
                LoginForm.#showRecaptcha(form, data.recaptchaSiteKey);
                Working.stop(submit_button);
                return;
            }

            if (data.twoFactorRequired) {
                window.location = ClientConfig.siteURL() + '/login';
                return;
            }

            window.location = ClientConfig.siteURL() + '/';
        });
    }

    // --- reCAPTCHA helpers (moved from main.js) ---

    static #loadRecaptchaApi() {
        if (window.grecaptcha && window.grecaptcha.render) return Promise.resolve();
        if (LoginForm.#recaptchaLoading) return LoginForm.#recaptchaLoading;

        LoginForm.#recaptchaLoading = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = 'https://www.google.com/recaptcha/api.js?render=explicit';
            script.async = true;
            script.defer = true;
            script.addEventListener('load', () => {
                const wait = () => {
                    if (window.grecaptcha && window.grecaptcha.render) resolve();
                    else setTimeout(wait, 50);
                };
                wait();
            });
            script.addEventListener('error', () => reject(new Error('reCAPTCHA failed to load')));
            document.head.appendWithSpace(script);
        });

        return LoginForm.#recaptchaLoading;
    }

    static async #showRecaptcha(form, site_key) {
        if (form.recaptchaWidgetId !== undefined) {
            window.grecaptcha.reset(form.recaptchaWidgetId);
            return;
        }

        const notice = document.createElement('p');
        notice.className = 'LoginRecaptchaNotice';
        notice.textContent = Strings.for('LoginClient').verificationRequired || '';

        const container = document.createElement('div');
        container.className = 'LoginRecaptcha';

        const submit_button = form.querySelector('button[type="submit"]');
        form.insertBeforeWithSpace(notice, submit_button);
        form.insertBeforeWithSpace(container, submit_button);

        try {
            await LoginForm.#loadRecaptchaApi();
            form.recaptchaWidgetId = window.grecaptcha.render(container, { sitekey: site_key });
        } catch (error) {
            Toast.show(Strings.for('LoginClient').verificationFailed || '');
        }
    }

    static #resetRecaptcha(form) {
        if (form.recaptchaWidgetId !== undefined && window.grecaptcha) {
            window.grecaptcha.reset(form.recaptchaWidgetId);
        }
    }
}

ReadyHandler.add(LoginForm.init);

    return { LoginForm };
})();
export const LoginForm = LoginFormModule.LoginForm;

// LogoutEverywherePanel.js
const LogoutEverywherePanelModule = (() => {
class LogoutEverywherePanel {
    static init() {
        const panel = document.querySelector('.LogoutEverywherePanel');
        if (!panel) {
            return;
        }

        const button = panel.querySelector('.LogoutEverywhereButton');
        if (!button) {
            return;
        }

        button.addEventListener('click', async (event) => {
            event.preventDefault();

            if (!(await Dialog.confirm(
                Strings.for('ClientStatus').signOutEverywhere || ''
            ))) {
                return;
            }

            button.textContent = Strings.for('ClientStatus').signingOut || '';
            Working.start(button);

            // Api.post answers null rather than throwing, so this is a check
            // and not a catch. It said "Done" and left for the home page on a
            // request that never landed, which is the worst thing this
            // particular button can get wrong: somebody signing every device
            // out is somebody who thinks another person is holding one.
            const signed_out = await Api.post('/api/logout-everywhere', {});

            if (!signed_out) {
                button.textContent = Strings.for('ClientStatus').failed || '';
                Working.stop(button);

                return;
            }

            button.textContent = Strings.for('ClientStatus').done || '';
            window.location.href = '/';
        });
    }
}

ReadyHandler.add(LogoutEverywherePanel.init);

    return { LogoutEverywherePanel };
})();
export const LogoutEverywherePanel = LogoutEverywherePanelModule.LogoutEverywherePanel;

// LogoutForm.js
const LogoutFormModule = (() => {
class LogoutForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.LogoutForm');
            if (!form) return;
            event.preventDefault();

            const submit_button = form.querySelector('button[type="submit"]');
            Working.start(submit_button);

            // Left working on the way out: the page is about to be replaced,
            // and a button springing back first reads as a press that failed.
            if (await Api.post('/api/logout', new FormData(form)) === null) {
                Working.stop(submit_button);

                return;
            }

            window.location = ClientConfig.siteURL() + '/';
        });
    }
}

ReadyHandler.add(LogoutForm.init);

    return { LogoutForm };
})();
export const LogoutForm = LogoutFormModule.LogoutForm;

// MailSettingsForm.js
const MailSettingsFormModule = (() => {
// MailSettingsForm.js
class MailSettingsForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.MailSettingsForm');
            if (!form) return;
            event.preventDefault();
            const submit_button = form.querySelector('button[type="submit"]');
            Working.start(submit_button);
            const data = await Api.post('/api/mail-settings', {
                mailFromAddress: form.querySelector('[name="mailFromAddress"]').value,
                mailFromName: form.querySelector('[name="mailFromName"]').value,
                smtpHost: form.querySelector('[name="smtpHost"]').value,
                smtpPort: form.querySelector('[name="smtpPort"]').value,
                smtpUsername: form.querySelector('[name="smtpUsername"]').value,
                smtpPassword: form.querySelector('[name="smtpPassword"]').value,
                smtpEncryption: form.querySelector('[name="smtpEncryption"]').value,
            }, { form });
            Working.stop(submit_button);
            if (data) Toast.show(Strings.for('ClientStatus').settingsSaved || '');
        });
    }
}

ReadyHandler.add(MailSettingsForm.init);

    return { MailSettingsForm };
})();
export const MailSettingsForm = MailSettingsFormModule.MailSettingsForm;

// MapScrubber.js
const MapScrubberModule = (() => {
/**
 * The time control under the map. Owns the slider, the mode buttons and the
 * playback timer; PostMap hands it the posts and a callback, and it hands back
 * the subset that should be on the map. It knows nothing about Leaflet.
 *
 * Two readings of the same handle: cumulative (everything up to that moment,
 * the default - so the map opens complete and dragging back removes pins) and
 * window (only what was posted around it, which finds bursts).
 */
class MapScrubber {
    static #STEPS = 1000;
    static #PLAY_MS = 60;

    /** Fraction of the whole span each side of the handle in window mode. */
    static #WINDOW_FRACTION = 0.05;

    #element;
    #range;
    #label;
    #onChange;
    #posts = [];
    #first = 0;
    #last = 0;
    #mode = 'cumulative';
    #timer = null;
    #bound = false;

    constructor(element, onChange) {
        this.#element = element;
        this.#range = element.querySelector('.MapScrubberRange');
        this.#label = element.querySelector('.MapScrubberLabel');
        this.#onChange = onChange;
    }

    /**
     * Hands the scrubber the located posts. Anything without a usable date is
     * dropped rather than being parked at the epoch, which would stretch the
     * whole span back to 1970 for one bad row.
     */
    start(posts) {
        this.#posts = posts
            .map((post) => ({ post, time: parse_server_date(post.createdAt)?.getTime() }))
            .filter((entry) => Number.isFinite(entry.time));

        this.#activate();
    }

    /**
     * Shows the control once there's a span worth scrubbing, and recomputes the
     * bounds from whatever posts are known. Safe to call again as posts arrive;
     * the listeners are only bound the first time.
     */
    #activate() {
        // A single post spans no time. Stay hidden rather than offering a
        // slider that can only ever mean one thing.
        if (this.#posts.length < 2) {
            return;
        }

        const times = this.#posts.map((entry) => entry.time);
        this.#first = Math.min(...times);
        this.#last = Math.max(...times);

        this.#element.querySelector('.MapScrubberFirst').textContent = MapScrubber.#formatDate(this.#first);
        this.#element.querySelector('.MapScrubberLast').textContent = MapScrubber.#formatDate(this.#last);

        if (!this.#bound) {
            this.#bind();
            this.#bound = true;
        }

        this.#element.classList.add('Active');
        this.#apply();
    }

    /**
     * Takes a post made after the initial load. Extends the span to reach it
     * rather than leaving it outside the slider's range, where cumulative mode
     * would hide a post the viewer just made.
     */
    add(post) {
        const time = parse_server_date(post.createdAt)?.getTime();

        if (!Number.isFinite(time)) {
            return;
        }

        this.#posts.push({ post, time });

        // Recomputes from every known post rather than folding into the current
        // bounds, which would drag the span back to the epoch if the control
        // had never started and its bounds were still zero.
        this.#activate();
    }

    #bind() {
        this.#range.addEventListener('input', () => {
            this.#stop();
            this.#apply();
        });

        this.#element.querySelectorAll('.MapScrubberModeButton').forEach((button) => {
            button.addEventListener('click', () => {
                this.#mode = button.dataset.mode;
                this.#element.querySelectorAll('.MapScrubberModeButton').forEach((other) => {
                    other.classList.toggle('Active', other === button);
                });
                this.#apply();
            });
        });

        this.#element.querySelector('.MapScrubberPlay').addEventListener('click', () => this.#togglePlay());
    }

    #togglePlay() {
        if (this.#timer !== null) {
            this.#stop();
            return;
        }

        // Replaying from where the handle already sits would end instantly when
        // it's parked at "now", which is where it starts.
        if (Number(this.#range.value) >= MapScrubber.#STEPS) {
            this.#range.value = 0;
        }

        this.#element.querySelector('.MapScrubberPlay').textContent = Strings.for('MapScrubber', { pause: 'Pause' }).pause;

        this.#timer = setInterval(() => {
            const next = Number(this.#range.value) + MapScrubber.#STEPS / 120;

            if (next >= MapScrubber.#STEPS) {
                this.#range.value = MapScrubber.#STEPS;
                this.#apply();
                this.#stop();
                return;
            }

            this.#range.value = next;
            this.#apply();
        }, MapScrubber.#PLAY_MS);
    }

    #stop() {
        if (this.#timer !== null) {
            clearInterval(this.#timer);
            this.#timer = null;
        }

        this.#element.querySelector('.MapScrubberPlay').textContent = Strings.for('MapScrubber', { play: 'Play' }).play;
    }

    /** The moment the handle currently points at. */
    #handleTime() {
        const fraction = Number(this.#range.value) / MapScrubber.#STEPS;

        return this.#first + (this.#last - this.#first) * fraction;
    }

    #apply() {
        const at = this.#handleTime();
        const span = (this.#last - this.#first) * MapScrubber.#WINDOW_FRACTION;

        const visible = this.#mode === 'window'
            ? this.#posts.filter((entry) => Math.abs(entry.time - at) <= span)
            : this.#posts.filter((entry) => entry.time <= at);

        const words = Strings.for('MapScrubber', {
            cumulativeLabel: { one: 'Posted up to {date} — {count} post', other: 'Posted up to {date} — {count} posts' },
            windowLabel: { one: 'Posted around {date} — {count} post', other: 'Posted around {date} — {count} posts' },
        });

        const forms = this.#mode === 'window' ? words.windowLabel : words.cumulativeLabel;

        this.#label.textContent = Strings.plural(forms, visible.length).replace('{date}', MapScrubber.#formatDate(at));

        this.#onChange(visible.map((entry) => entry.post));
    }

    /**
     * Through DateFormat rather than toLocaleDateString(): that renders in
     * the browser's own language from the browser's own tables, and this
     * label sits on a page whose every other date is the site's locale
     * reading the locale file.
     */
    static #formatDate(time) {
        return DateFormat.short(new Date(time));
    }

}

    return { MapScrubber };
})();
export const MapScrubber = MapScrubberModule.MapScrubber;

// MapSettingsForm.js
const MapSettingsFormModule = (() => {
class MapSettingsForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.MapSettingsForm');

            if (!form) {
                return;
            }

            event.preventDefault();

            const submit_button = form.querySelector('button[type="submit"]');

            if (submit_button) {
                Working.start(submit_button);
            }

            const data = await Api.post('/api/map-settings', {
                mapTileURL: form.querySelector('[name="mapTileURL"]').value,
                mapTileAPIKey: form.querySelector('[name="mapTileAPIKey"]').value,
                mapTileAttribution: form.querySelector('[name="mapTileAttribution"]').value,
            });

            if (submit_button) {
                Working.stop(submit_button);
            }

            if (data !== null) {
                Toast.show(Strings.for('ClientStatus').mapSaved || '');
            }
        });
    }
}

ReadyHandler.add(MapSettingsForm.init);

    return { MapSettingsForm };
})();
export const MapSettingsForm = MapSettingsFormModule.MapSettingsForm;

// MessageComposer.js
const MessageComposerModule = (() => {
class MessageComposer {
    /**
     * Keeps the page's bottom padding equal to however tall the composer
     * actually is.
     *
     * It is fixed to the bottom of the window, so nothing in the flow knows it
     * is there and the page has to leave the room by hand. A fixed figure only
     * holds while the composer is one fixed size, and it is not: the video
     * call button appears when the other person turns up and adds a row, and
     * the last messages in the thread end up underneath it.
     *
     * Measured rather than counted up from what is in there, so anything else
     * that ever grows it - a wrapped chip, a taller textarea - is covered
     * without this needing to know about it.
     */
    static #reserveRoomFor(composer) {
        const measure = () => {
            document.body.style.setProperty('--composer-height', composer.offsetHeight + 'px');
        };

        // Once up front, so the room is right whether or not anything below
        // this is available to watch for changes.
        measure();

        if (typeof ResizeObserver !== 'undefined') {
            new ResizeObserver(measure).observe(composer);
        }
    }

    static init() {
        // --- Emoji picker (identical to Composer's wiring) ---
        const messageForm = document.querySelector('.MessageComposer');
        if (messageForm) {
            MessageComposer.#reserveRoomFor(messageForm);

            const emojiWrapper = messageForm.querySelector('.EmojiPicker');
            if (emojiWrapper) {
                EmojiPicker.setup(emojiWrapper);
            }
        }

        // --- The privacy chip pops its full explanation ---
        document.addEventListener('click', (event) => {
            const chip = event.target.closest('.MessagePrivacyButton');
            if (!chip) return;
            Dialog.alert(chip.dataset.privacyExplanation);
        });

        // --- Click outside closes the panel ---
        document.addEventListener('click', (event) => {
            if (event.target.closest('.EmojiPickerTriggerButton')) return;
            if (event.target.closest('emoji-picker')) return;
            document.querySelectorAll('emoji-picker.Active').forEach(panel => panel.classList.remove('Active'));
        });

        // --- Submit on Enter (without Shift) ---
        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' || event.shiftKey) return;
            const textarea = event.target.closest('.MessageComposer textarea');
            if (!textarea) return;
            event.preventDefault();
            textarea.closest('form').requestSubmit();
        });

        // --- AJAX submit ---
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.MessageComposer');
            if (!form) return;
            event.preventDefault();

            const submit_button = form.querySelector('button[type="submit"]');
            const body_input = form.querySelector('[name="body"]');
            const recipient_id = form.querySelector('[name="recipientId"]').value;

            // In an encrypted conversation every message is encrypted - a
            // locked thread prompts for the passphrase rather than quietly
            // falling back to plaintext. The unlock form is only rendered for
            // a conversation both sides hold keys for, so its presence is what
            // says this thread is encrypted.
            const payload = { recipientId: recipient_id };
            const unlock_form = document.querySelector('.MessageUnlockForm');

            if (unlock_form !== null) {
                if (MessageCrypto.threadKey() === null) {
                    Toast.show(Strings.for('ClientStatus').unlockConversation || '');
                    document.querySelector('.MessageUnlockForm [name="messagePassphrase"]')?.focus();
                    return;
                }

                if (body_input.value.trim() === '') return;

                payload.envelope = await MessageCrypto.encrypt(MessageCrypto.threadKey(), body_input.value);
            } else {
                payload.body = body_input.value;
            }

            Working.start(submit_button);

            try {
                const result = await Api.post('/api/send-message', payload, { form });

                if (result === null) return;

                const list = list_in(document.querySelector('main'), 'MessageList');

                const message = Message.fromData(result);
                const element = message.toElement();
                RelativeTime.refresh(element);
                list.appendWithSpace(list_item(element));

                body_input.value = '';
                window.scrollTo({ top: document.body.scrollHeight, left: 0, behavior: 'instant' });
            } finally {
                Working.stop(submit_button);
            }
        });
    }
}

ReadyHandler.add(MessageComposer.init);

    return { MessageComposer };
})();
export const MessageComposer = MessageComposerModule.MessageComposer;

// MessageKeyFingerprint.js
const MessageKeyFingerprintModule = (() => {
/**
 * The safety code for an encrypted conversation, and the memory of having
 * checked it.
 *
 * Computed here from the keys this browser is encrypting with, never taken
 * from the server: the server is what introduces the two sides to each other's
 * keys, so a code it supplied would agree with whatever it had handed over. It
 * cannot make both sides' codes match without holding the two real keys, which
 * is what makes reading them to each other worth doing.
 *
 * A confirmed code is remembered in this browser, for the same reason - a note
 * kept on the server could be rewritten by the server it is meant to catch. So
 * verification is per browser, and a code that changes afterwards is called out
 * rather than quietly accepted.
 */
class MessageKeyFingerprint {
    static init() {
        const block = document.querySelector('.MessageKeyFingerprint');
        const form = document.querySelector('.MessageUnlockForm');
        const thread = document.querySelector('.MessageComposer[data-other-user-id]');

        if (block === null || form === null || thread === null) return;

        MessageKeyFingerprint.#show(block, form, thread.dataset.otherUserId);

        document.addEventListener('click', (event) => {
            if (!event.target.closest('.MessageKeyVerifyButton')) return;

            const code = block.querySelector('.MessageKeyFingerprintCode').textContent;

            if (code === '') return;

            localStorage.setItem(MessageKeyFingerprint.#storageKey(thread.dataset.otherUserId), code);
            MessageKeyFingerprint.#markVerified(block);
            Toast.show(Strings.for('ClientStatus').markedVerified || '');
        });
    }

    static async #show(block, form, other_user_id) {
        const code = await MessageCrypto.fingerprint(
            JSON.parse(form.dataset.otherPublicKey),
            JSON.parse(form.dataset.ownPublicKey)
        );

        block.querySelector('.MessageKeyFingerprintCode').textContent = code;

        const confirmed = localStorage.getItem(MessageKeyFingerprint.#storageKey(other_user_id));

        if (confirmed === null) return;

        if (confirmed === code) {
            MessageKeyFingerprint.#markVerified(block);

            return;
        }

        // Either one of them reset their keys, or somebody replaced one. This
        // cannot tell which, and says so rather than guessing.
        block.classList.add('Changed');

        const warning = document.createElement('p');
        warning.className = 'MessageKeyFingerprintWarning';
        warning.textContent = Strings.for('MessageKeyFingerprint').changed || '';
        block.prepend(warning);
    }

    static #markVerified(block) {
        block.classList.add('Verified');
        block.classList.remove('Changed');

        const button = block.querySelector('.MessageKeyVerifyButton');

        if (button !== null) {
            button.remove();
        }

        const done = document.createElement('p');
        done.className = 'MessageKeyFingerprintVerified';
        done.textContent = Strings.for('MessageKeyFingerprint', { verified: 'You have checked this code.' }).verified;
        block.appendWithSpace(done);
    }

    static #storageKey(other_user_id) {
        return 'messageKeyVerified:' + other_user_id;
    }
}

ReadyHandler.add(MessageKeyFingerprint.init);

    return { MessageKeyFingerprint };
})();
export const MessageKeyFingerprint = MessageKeyFingerprintModule.MessageKeyFingerprint;

// MessageTranslateButton.js
const MessageTranslateButtonModule = (() => {
/**
 * Reading one received message in the reader's own language.
 *
 * Nothing here runs on its own. A conversation is not something to hand to a
 * translator on the reader's behalf, so this waits for the button, asks once
 * whether they understand where the words are going, and translates one
 * message at a time. Pressing it again puts the original back.
 */
class MessageTranslateButton {
    /** Remembers that the notice has been read, so it is said once. */
    static NOTICE_KEY = 'translation-notice-read';

    /** Mirrors MessageTranslateButton.php's two glyphs. */
    static TRANSLATE = '🌐';
    static SHOW_ORIGINAL = '↩️';

    /**
     * What each translated message said before, so pressing the button again
     * can put it back.
     *
     * Held here rather than on the element: nothing renders it, nothing reads
     * it but this, and a message's own words parked in an attribute are one
     * copy of them nobody expects to find there. Weak, so a message scrolled
     * out of a conversation and dropped takes its original with it.
     */
    static #originals = new WeakMap();

    static init() {
        document.addEventListener('click', async (event) => {
            const button = event.target.closest('.MessageTranslateButton');

            if (!button) return;

            const message = button.closest('.Message');

            if (!message) return;

            // A message still sealed says only the placeholder, and there is
            // no sense translating that.
            if (message.classList.contains('Locked')) return;

            const body = message.querySelector('.MessageBody');

            if (!body) return;

            if (MessageTranslateButton.#originals.has(body)) {
                MessageTranslateButton.#restore(button, body);

                return;
            }

            if (!await MessageTranslateButton.#agreed()) return;

            await MessageTranslateButton.#translate(button, message, body);
        });
    }

    /**
     * The one-time notice. Translating sends the words to the server, which is
     * a real change in who has seen them - said before the first one, not
     * discovered after it.
     */
    static async #agreed() {
        if (localStorage.getItem(MessageTranslateButton.NOTICE_KEY) === '1') return true;

        const words = Strings.for('MessageTranslationNotice');

        if (!await Dialog.confirm(words.body)) return false;

        localStorage.setItem(MessageTranslateButton.NOTICE_KEY, '1');

        return true;
    }

    static async #translate(button, message, body) {
        const original = body.textContent;

        const result = await Api.post('/api/translate-message', {
            messageId: Number(button.dataset.messageId),
            language: page_language(),
            // Only ever read for a message the server cannot open itself; for
            // every other one it reads its own copy and ignores this.
            text: message.classList.contains('Encrypted') ? original : '',
        });

        if (!result) return;

        MessageTranslateButton.#originals.set(body, original);
        body.textContent = String(result.body);
        body.classList.add('MachineTranslation');
        ToggleButton.select(button, MessageTranslateButton.SHOW_ORIGINAL);
    }

    static #restore(button, body) {
        body.textContent = MessageTranslateButton.#originals.get(body);
        MessageTranslateButton.#originals.delete(body);
        body.classList.remove('MachineTranslation');
        ToggleButton.select(button, MessageTranslateButton.TRANSLATE);
    }
}

ReadyHandler.add(MessageTranslateButton.init);

    return { MessageTranslateButton };
})();
export const MessageTranslateButton = MessageTranslateButtonModule.MessageTranslateButton;

// MessageUnlockForm.js
const MessageUnlockFormModule = (() => {
/**
 * Opens an encrypted conversation: unwraps the viewer's private key with
 * their passphrase (all in the browser - see HTMLObjects.js's MessageCrypto), derives the
 * conversation key, and decrypts every envelope on the page. Once the tab
 * holds the unlocked key, the form hides itself and later page loads unlock
 * silently.
 */
class MessageUnlockForm {
    static init() {
        const stored = MessageCrypto.loadUnlocked();

        if (stored !== null) {
            MessageUnlockForm.#activate(stored);
        }

        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.MessageUnlockForm');
            if (!form) return;
            event.preventDefault();

            const passphrase_input = form.querySelector('[name="messagePassphrase"]');
            const wrapped = JSON.parse(form.dataset.wrappedPrivateKey);
            const private_jwk = await MessageCrypto.unwrapPrivateKey(wrapped, passphrase_input.value);

            if (private_jwk === null) {
                Toast.show(Strings.for('ClientStatus').unlockFailed || '');
                passphrase_input.focus();
                return;
            }

            MessageCrypto.storeUnlocked(private_jwk);
            passphrase_input.value = '';
            await MessageUnlockForm.#activate(private_jwk);
        });
    }

    static async #activate(private_jwk) {
        const form = document.querySelector('.MessageUnlockForm');
        if (form === null) return;

        try {
            MessageCrypto.setThreadKey(await MessageCrypto.conversationKey(private_jwk, JSON.parse(form.dataset.otherPublicKey)));
        } catch {
            // A stored key that doesn't parse as a P-256 key (say, after a
            // reset in another tab) just leaves the thread locked - the form
            // stays up and a fresh passphrase replaces it.
            MessageCrypto.clearUnlocked();
            return;
        }

        document.querySelectorAll('.MessageUnlockForm').forEach((form) => { form.hidden = true; });

        document.querySelectorAll('.Message[data-cipher-envelope]').forEach((article) => {
            Message.decryptInto(article);
        });
    }
}

ReadyHandler.add(MessageUnlockForm.init);

    return { MessageUnlockForm };
})();
export const MessageUnlockForm = MessageUnlockFormModule.MessageUnlockForm;

// NavMenu.js
const NavMenuModule = (() => {
/**
 * Tells assistive tech whether the mobile menu is open.
 *
 * The menu is a checkbox and a label: the CSS reveals the stacked links while
 * the box is checked, so navigation works with no JavaScript at all. What it
 * cannot do is say so - CSS has no way to set aria-expanded, and a control
 * that opens something is supposed to announce whether it is currently open.
 *
 * So this adds only that. It reads the checkbox and mirrors it; it never
 * intercepts the click, never moves the state anywhere else, and never touches
 * the class the CSS keys on. With this file missing, or broken, or blocked,
 * the menu behaves exactly as it did before - which is the whole point of
 * doing it this way rather than rebuilding the menu in JavaScript.
 */
class NavMenu {
    static init() {
        const toggle = document.getElementById('NavToggle');

        if (!toggle) return;

        const reflect = () => toggle.setAttribute('aria-expanded', toggle.checked ? 'true' : 'false');

        reflect();

        // change rather than click: the label, the keyboard and anything else
        // that flips a checkbox all raise it, and none of them are this
        // module's business to know about.
        toggle.addEventListener('change', reflect);
    }
}

ReadyHandler.add(NavMenu.init);

    return { NavMenu };
})();
export const NavMenu = NavMenuModule.NavMenu;

// NearbyLocationPrompt.js
const NearbyLocationPromptModule = (() => {
/**
 * The ways /locations learns where "near" is: the "Use my location" button, and
 * a place search fed by the local gazetteer. Both end the same way - a
 * reload with coordinates in the query string, so the result is shareable
 * and bookmarkable rather than trapped in client state. The search commits
 * ONLY when a suggestion is clicked; typing alone never navigates.
 */
class NearbyLocationPrompt {
    static #debounceId = null;

    static init() {
        const input = document.querySelector('.NearbyPlaceSearchInput');

        if (input) {
            input.addEventListener('input', () => {
                clearTimeout(NearbyLocationPrompt.#debounceId);
                NearbyLocationPrompt.#debounceId = setTimeout(() => NearbyLocationPrompt.#suggest(input), 250);
            });
        }

        document.addEventListener('click', (event) => {
            const suggestion = event.target.closest('.NearbyPlaceSuggestion');

            if (suggestion) {
                // Straight to the place's own canonical page.
                window.location.href = ClientConfig.siteURL() + '/locations/' + suggestion.dataset.placeId;
                return;
            }

            // A click anywhere else puts the suggestions away.
            if (!event.target.closest('.NearbyPlaceSearch')) {
                document.querySelector('.NearbyPlaceSuggestions')?.remove();
            }

            const button = event.target.closest('.NearbyLocationButton');

            if (!button) {
                return;
            }

            const words = Strings.for('NearbyLocationPrompt', {
                useMyLocation: 'Use My Location',
                locating: 'Locating…',
                noGeolocation: 'Your browser can\'t share a location.',
                locationError: 'Could not get your location. Check your browser\'s location permission.',
            });

            if (!navigator.geolocation) {
                Toast.show(words.noGeolocation);
                return;
            }

            Working.start(button);
            button.textContent = words.locating;

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const parameters = new URLSearchParams({
                        lat: position.coords.latitude,
                        lng: position.coords.longitude,
                    });

                    window.location.search = parameters.toString();
                },
                () => {
                    Working.stop(button);
                    button.textContent = words.useMyLocation;
                    Toast.show(words.locationError);
                },
                { enableHighAccuracy: true, timeout: 10000 }
            );
        });
    }

    /**
     * Asks the gazetteer what the typed prefix could mean and lays the
     * answers under the box. The list exists only while it has entries.
     */
    static async #suggest(input) {
        const query = input.value.trim();
        document.querySelector('.NearbyPlaceSuggestions')?.remove();

        // Counted the way the server counts it, so the box asks exactly when
        // there is an answer to be had.
        if (Array.from(query).length < ClientConfig.get('placeSearchMinimumLength')) return;

        const result = await Api.post('/api/search-places', { q: query });

        if (!result || result.places.length === 0) return;
        if (input.value.trim() !== query) return;

        const list = document.createElement('ul');
        list.className = 'NearbyPlaceSuggestions';

        for (const place of result.places) {
            const item = document.createElement('li');

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'NearbyPlaceSuggestion';
            button.textContent = place.label;
            button.dataset.placeId = place.placeId;
            item.appendWithSpace(button);

            list.appendWithSpace(item);
        }

        input.closest('.NearbyPlaceSearch').appendWithSpace(list);
    }
}

ReadyHandler.add(NearbyLocationPrompt.init);

    return { NearbyLocationPrompt };
})();
export const NearbyLocationPrompt = NearbyLocationPromptModule.NearbyLocationPrompt;

// NotificationTestPanel.js
const NotificationTestPanelModule = (() => {
class NotificationTestPanel {
    static init() {
        const panel = document.querySelector('.NotificationTestPanel');
        if (!panel) {
            return;
        }

        const button = panel.querySelector('button');
        if (!button) {
            return;
        }

        // The button's own resting label lives in NotificationTestPanel.php;
        // read here too so the three states this method cycles it through
        // stay in the same language rather than falling back to English
        // partway through.
        const words = Strings.for('NotificationTestPanel', {
            button: 'Send Test Notification',
            sending: 'Sending…',
            sent: 'Sent!',
            failed: 'Failed',
        });

        button.addEventListener('click', async (event) => {
            event.preventDefault();
            button.textContent = words.sending;
            Working.start(button);

            // Api.post answers null rather than throwing, so this is a check
            // and not a catch. Saying it was sent when it was not is the one
            // thing a button for testing notifications must not do.
            const sent = await Api.post('/api/send-test-notification', {});

            if (!sent) {
                button.textContent = words.failed;
                Working.stop(button);

                return;
            }

            button.textContent = words.sent;

            setTimeout(() => {
                button.textContent = words.button;
                Working.stop(button);
            }, 2000);
        });
    }
}

ReadyHandler.add(NotificationTestPanel.init);

    return { NotificationTestPanel };
})();
export const NotificationTestPanel = NotificationTestPanelModule.NotificationTestPanel;

// OpenRouterSettingsForm.js
const OpenRouterSettingsFormModule = (() => {
class OpenRouterSettingsForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.OpenRouterSettingsForm');

            if (!form) {
                return;
            }

            event.preventDefault();

            const submit_button = form.querySelector('button[type="submit"]');

            if (submit_button) {
                Working.start(submit_button);
            }

            const data = await Api.post('/api/openrouter-settings', {
                openRouterAPIKey: form.querySelector('[name="openRouterAPIKey"]').value,
                openRouterModel: form.querySelector('[name="openRouterModel"]').value,
                openRouterNeverSpend: form.querySelector('[name="openRouterNeverSpend"]').checked,
                clearOpenRouterAPIKey: form.querySelector('[name="clearOpenRouterAPIKey"]')?.checked ?? false,
            });

            if (submit_button) {
                Working.stop(submit_button);
            }

            if (data !== null) {
                Toast.show(Strings.for('ClientStatus').openRouterSaved || '');
            }
        });
    }
}

ReadyHandler.add(OpenRouterSettingsForm.init);

    return { OpenRouterSettingsForm };
})();
export const OpenRouterSettingsForm = OpenRouterSettingsFormModule.OpenRouterSettingsForm;

// PasswordChangeForm.js
const PasswordChangeFormModule = (() => {
/**
 * Changing your own password.
 *
 * Api is handed the form, so a refusal that names the boxes it is about -
 * which of the three was wrong, and all of them at once - is written under
 * those boxes instead of thrown at the corner of the screen.
 */
class PasswordChangeForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.PasswordChangeForm');
            if (!form) return;
            event.preventDefault();

            const submit_button = form.querySelector('button[type="submit"]');
            Working.start(submit_button);

            try {
                const data = await Api.post('/api/change-password', {
                    currentPassword: form.querySelector('[name="currentPassword"]').value,
                    newPassword: form.querySelector('[name="newPassword"]').value,
                    confirmPassword: form.querySelector('[name="confirmPassword"]').value,
                }, { form });

                if (!data) return;

                form.reset();
                Toast.show(Strings.for('ClientStatus').passwordChanged || '');
            } finally {
                Working.stop(submit_button);
            }
        });
    }
}

ReadyHandler.add(PasswordChangeForm.init);

    return { PasswordChangeForm };
})();
export const PasswordChangeForm = PasswordChangeFormModule.PasswordChangeForm;

// PasswordResetForm.js
const PasswordResetFormModule = (() => {
class PasswordResetForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.PasswordResetForm');
            if (!form) return;
            event.preventDefault();

            const submit_button = form.querySelector('button[type="submit"]');
            Working.start(submit_button);

            const data = await Api.post('/api/reset-password', {
                token: form.querySelector('[name="token"]').value,
                newPassword: form.querySelector('[name="newPassword"]').value,
                confirmPassword: form.querySelector('[name="confirmPassword"]').value,
            }, { form });

            if (!data) {
                Working.stop(submit_button);
                return;
            }

            if (!data.reset) {
                Working.stop(submit_button);
                Toast.show(Strings.for('ClientStatus').passwordUnchanged || '');
                return;
            }

            const notice = document.createElement('p');
            const words = Strings.for('ClientStatus');
            notice.textContent = words.passwordReset || '';

            const login_link = document.createElement('a');
            login_link.href = ClientConfig.siteURL() + '/login';
            login_link.textContent = words.login || '';

            form.replaceWith(notice, login_link);
        });
    }
}

ReadyHandler.add(PasswordResetForm.init);

    return { PasswordResetForm };
})();
export const PasswordResetForm = PasswordResetFormModule.PasswordResetForm;

// PasswordResetRequestForm.js
const PasswordResetRequestFormModule = (() => {
class PasswordResetRequestForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.PasswordResetRequestForm');
            if (!form) return;
            event.preventDefault();

            const submit_button = form.querySelector('button[type="submit"]');
            Working.start(submit_button);

            const data = await Api.post('/api/forgot-password', {
                email: form.querySelector('[name="email"]').value,
            });

            Working.stop(submit_button);

            if (!data) return;

            const notice = document.createElement('p');
            notice.textContent = Strings.for('ClientStatus').resetSent || '';
            form.replaceWith(notice);
        });
    }
}

ReadyHandler.add(PasswordResetRequestForm.init);

    return { PasswordResetRequestForm };
})();
export const PasswordResetRequestForm = PasswordResetRequestFormModule.PasswordResetRequestForm;

// PostMap.js
const PostMapModule = (() => {
/**
 * The /map view: a Leaflet map of every geotagged post, clustered. Reads the
 * tile source from the container's data attributes (set server-side from the
 * admin Map settings) and fetches the points from /api/map-posts. Leaflet and
 * its markercluster plugin are plain global scripts loaded via MapAssets, so
 * this reads window.L rather than importing anything.
 *
 * Clicking the map drops a pin and opens a small menu out of it, because a click
 * is ambiguous - it might mean "post here" or "show me what's here" - so it asks
 * rather than assuming. Browsing what's nearby needs no account; a logged-in
 * viewer additionally gets "Post here", which fills the composer below the map
 * with those coordinates, so a post can be filed at a place being looked at
 * rather than only where the browser happens to be.
 */
class PostMap {
    static #pendingMarker = null;

    // Shared so a post made on this page can be added to the map properly -
    // into the same cluster, with the same popup, and known to the time
    // control - rather than as a loose pin that ignores all three.
    static #cluster = null;
    static #markers = new Map();
    static #scrubber = null;

    static init() {
        const container = document.querySelector('.PostMap');

        if (!container || typeof L === 'undefined') {
            return;
        }

        // Linked to a point (from a post's place line) opens there, zoomed in
        // enough to read a neighbourhood; otherwise the whole world.
        const centre = Coordinates.parse(container.dataset.centerLatitude, container.dataset.centerLongitude);
        const map = centre === null
            ? L.map(container).setView([20, 0], 2)
            : L.map(container).setView([centre.latitude, centre.longitude], 13);

        L.tileLayer(container.dataset.tileUrl, {
            attribution: container.dataset.tileAttribution,
            maxZoom: 19,
        }).addTo(map);

        // An explicit centre wins: fitting the pins would immediately pull the
        // view back off the point that was linked to.
        PostMap.#loadPosts(map, centre);
        PostMap.#bindComposer(map);
    }

    /**
     * Wires clicking the map to dropping a pin. Everyone gets the pin and its
     * menu - browsing what's near a point needs no account - but only a
     * logged-in viewer has a composer to hand the coordinates to.
     */
    static async #bindComposer(map) {
        const form = document.querySelector('.MapComposer');

        // Imported only when there's a composer to drive, so a logged-out
        // viewer doesn't pull the whole editor chain down just to look.
        const { Composer } = form ? await import('/scripts/Controllers.js') : { Composer: null };

        // With a composer, clicks route through it and the pin is placed by the
        // locationchange event below - so the map and the form can't disagree,
        // whether a point was picked, the pin dragged, the Add/Remove Location
        // button used, or a post submitted. Without one, the pin is placed
        // directly since there's nothing to keep in step with.
        map.on('click', (event) => {
            const composer = form ? Composer.getInstance(form) : null;

            if (composer === null) {
                PostMap.#placePending(Composer, map, form, event.latlng.lat, event.latlng.lng);
                return;
            }

            composer.setLocation(event.latlng.lat, event.latlng.lng);
        });

        if (!form) {
            return;
        }

        form.addEventListener('composer:locationchange', (event) => {
            const { latitude, longitude } = event.detail;

            if (latitude === null) {
                // Cleared - by the Remove Location button, or by the pin's own
                // menu. The composer stays open so nothing typed is lost; the
                // post simply carries no location unless another point is picked.
                PostMap.#clearPending(map);
                return;
            }

            PostMap.#placePending(Composer, map, form, latitude, longitude);
        });

        // A successful post clears the pending pin and leaves a real one where
        // it landed, so the map reflects the new post without a reload.
        form.addEventListener('composer:posted', (event) => {
            const { post, latitude, longitude } = event.detail;

            PostMap.#clearPending(map);
            form.classList.remove('Active');

            if (latitude === '' || longitude === '') {
                return;
            }

            // A post carrying media isn't published yet - the upload worker
            // finishes and publishes it - so there's no post to pin. It shows
            // up on the next load, once it actually exists.
            if (post.processing) {
                return;
            }

            PostMap.addPost({
                postId: post.postId,
                latitude: Number(latitude),
                longitude: Number(longitude),
                title: post.title,
                createdAt: post.createdAt,
                authorName: post.author?.title || post.author?.slug || '',
                // The create-post payload has no 'url' - seeMoreURL is the same
                // permalink map-posts builds, so the popup links the same place
                // whether the pin arrived from the endpoint or was just made.
                url: post.seeMoreURL,
            });
        });
    }

    /**
     * Places the pending pin, or moves the existing one. Moving rather than
     * recreating is what lets a drag update the form without the pin being torn
     * out from under the cursor mid-gesture.
     */
    static #placePending(Composer, map, form, latitude, longitude) {
        if (PostMap.#pendingMarker !== null) {
            PostMap.#pendingMarker.setLatLng([latitude, longitude]);
            // The menu carries the coordinates and the nearby link, so it has to
            // be rebuilt when the pin moves or a drag leaves it pointing at
            // where the pin used to be.
            PostMap.#pendingMarker.setPopupContent(PostMap.#pinMenu(Composer, map, form, latitude, longitude));
            // Every (re)placement opens the menu - a pin sitting there closed
            // just looks like a mistake the viewer has to click to recover from.
            PostMap.#pendingMarker.openPopup();
            return;
        }

        PostMap.#pendingMarker = L.marker([latitude, longitude], { draggable: true, opacity: 0.7 })
            .addTo(map)
            .bindPopup(PostMap.#pinMenu(Composer, map, form, latitude, longitude))
            .on('dragend', (event) => {
                const position = event.target.getLatLng();
                const composer = form ? Composer.getInstance(form) : null;

                if (composer !== null) {
                    composer.setLocation(position.lat, position.lng);
                    return;
                }

                PostMap.#pendingMarker.setPopupContent(PostMap.#pinMenu(Composer, map, form, position.lat, position.lng));
                PostMap.#pendingMarker.openPopup();
            })
            .openPopup();
    }

    /**
     * The little menu that opens out of a dropped pin. A click on the map is
     * ambiguous - it might mean "post here" or "show me what's here" - so it
     * asks instead of assuming, and nothing scrolls until a choice is made.
     */
    static #pinMenu(Composer, map, form, latitude, longitude) {
        const menu = document.createElement('div');
        menu.className = 'MapPinMenu';

        const position = document.createElement('div');
        position.className = 'MapPinPosition';
        position.textContent = latitude.toFixed(4) + ', ' + longitude.toFixed(4);
        menu.appendWithSpace(position);

        // Only offered when there's somewhere to post from - a logged-out
        // viewer still gets the pin and the nearby link.
        if (form) {
            const post_here = document.createElement('button');
            post_here.type = 'button';
            post_here.className = 'Button';
            post_here.textContent = Strings.for('PostMapClient').postHere || '';
            post_here.addEventListener('click', () => {
                form.classList.add('Active');
                form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            });
            menu.appendWithSpace(post_here);
        }

        const nearby = document.createElement('a');
        nearby.className = 'Button';
        nearby.href = ClientConfig.siteURL() + '/locations/?lat=' + encodeURIComponent(latitude) + '&lng=' + encodeURIComponent(longitude);
        nearby.textContent = Strings.for('PostMapClient').postsNearby || '';
        menu.appendWithSpace(nearby);

        const clear = document.createElement('button');
        clear.type = 'button';
        clear.className = 'Button';
        clear.textContent = Strings.for('PostMapClient').clearPin || '';
        clear.addEventListener('click', () => {
            const composer = form ? Composer.getInstance(form) : null;

            if (composer !== null) {
                composer.setLocation(null, null);
                return;
            }

            PostMap.#clearPending(map);
        });
        menu.appendWithSpace(clear);

        return menu;
    }

    static #clearPending(map) {
        if (PostMap.#pendingMarker !== null) {
            map.removeLayer(PostMap.#pendingMarker);
            PostMap.#pendingMarker = null;
        }
    }

    static async #loadPosts(map, centre) {
        // Quiet: the map is drawn either way, and an empty one says as much as
        // a complaint would.
        const data = await Api.post('/api/map-posts', {}, { quiet: true });

        if (!data) return;

        const posts = data.posts;
        const cluster = L.markerClusterGroup();

        // Markers are built once and kept, keyed by post, so scrubbing swaps
        // which are in the cluster rather than rebuilding them - and a popup
        // left open on a pin survives the pin going away and coming back.
        const markers = new Map();

        for (const post of posts) {
            const marker = L.marker([post.latitude, post.longitude]);
            marker.bindPopup(PostMap.#popupElement(post));
            markers.set(post, marker);
        }

        cluster.addLayers([...markers.values()]);
        map.addLayer(cluster);

        PostMap.#cluster = cluster;
        PostMap.#markers = markers;

        if (centre === null && posts.length > 0) {
            map.fitBounds(cluster.getBounds(), { padding: [40, 40], maxZoom: 12 });
        }

        PostMap.#revealAt(cluster, markers, centre);

        PostMap.#bindScrubber(posts, cluster, markers);
    }

    /**
     * Opens the post the map was pointed at.
     *
     * Arriving from a post's place line centres the map on that post, but the
     * post itself may be inside a cluster bubble at that zoom - so the one
     * thing the reader came to see is the one thing they cannot see. This asks
     * the cluster to reveal it, which zooms or spiderfies as far as it needs
     * to, and then opens its popup.
     *
     * Only ever the marker at the requested point, and only when a point was
     * requested. Keying on where the map happens to be looking would pull
     * markers in and out of clusters as it was panned.
     */
    static #revealAt(cluster, markers, centre) {
        if (centre === null) {
            return;
        }

        // Coordinates are stored exactly and travel through the URL as written,
        // so this is a tolerance for the round trip through text rather than
        // for nearby posts - a tenth of a metre, not a neighbourhood.
        const samePlace = (position) => Math.abs(position.lat - centre.latitude) < 0.000001
            && Math.abs(position.lng - centre.longitude) < 0.000001;

        for (const marker of markers.values()) {
            if (samePlace(marker.getLatLng())) {
                cluster.zoomToShowLayer(marker, () => marker.openPopup());

                return;
            }
        }
    }

    /**
     * Puts a just-made post on the map the same way a fetched one arrives: in
     * the cluster, with a popup, and handed to the time control so scrubbing
     * treats it like any other post rather than leaving it stuck on screen.
     */
    static addPost(post) {
        if (PostMap.#cluster === null) {
            return;
        }

        const marker = L.marker([post.latitude, post.longitude]);
        marker.bindPopup(PostMap.#popupElement(post));

        PostMap.#markers.set(post, marker);
        PostMap.#cluster.addLayers([marker]);
        PostMap.#scrubber?.add(post);
    }

    /**
     * Hands the posts to the time control, if the page has one. Bulk add/remove
     * rather than per-marker: markercluster rebuilds its whole spatial index on
     * each individual change, which is visibly slow while a slider is moving.
     */
    static async #bindScrubber(posts, cluster, markers) {
        const element = document.querySelector('.MapScrubber');

        if (!element || posts.length === 0) {
            return;
        }

        const { MapScrubber } = await import('/scripts/Controllers.js');

        PostMap.#scrubber = new MapScrubber(element, (visible) => {
            const shown = new Set(visible.map((post) => markers.get(post)));
            const toRemove = [];
            const toAdd = [];

            for (const marker of markers.values()) {
                const on = cluster.hasLayer(marker);

                if (shown.has(marker) && !on) {
                    toAdd.push(marker);
                } else if (!shown.has(marker) && on) {
                    toRemove.push(marker);
                }
            }

            if (toRemove.length > 0) {
                cluster.removeLayers(toRemove);
            }

            if (toAdd.length > 0) {
                cluster.addLayers(toAdd);
            }
        });

        PostMap.#scrubber.start(posts);
    }

    static #popupElement(post) {
        const wrapper = document.createElement('div');
        wrapper.className = 'MapPopup';

        // Checked like every other href built from a payload. These addresses
        // are this server's own today, but the check is what keeps that from
        // being a thing somebody has to remember when the map one day carries
        // a post from somewhere else.
        const link = document.createElement('a');
        link.textContent = post.title || Strings.for('PostMapClient').viewPost || '';

        if (DeltaRenderer.isSafeLink(post.url, DeltaRenderer.ALLOWED_LINK_SCHEMES)) {
            link.href = post.url;
        }

        wrapper.appendWithSpace(link);

        const author = document.createElement('div');
        author.className = 'MapPinAuthor';
        author.textContent = (Strings.for('PostMapClient').byAuthor || '').replace('{name}', post.authorName);
        wrapper.appendWithSpace(author);

        return wrapper;
    }
}

ReadyHandler.add(PostMap.init);

    return { PostMap };
})();
export const PostMap = PostMapModule.PostMap;

// PostPinButton.js
const PostPinButtonModule = (() => {
/**
 * Pinning one of your own posts to the top of your profile.
 *
 * The button carries its own state, so it flips in place rather than needing
 * the page rebuilt. The profile itself is only rebuilt on the next load - the
 * pinned section lives above the feed and reordering it live would move
 * whatever the reader is looking at.
 */
class PostPinButton {
    static init() {
        document.addEventListener('click', (event) => {
            const button = event.target.closest('.PostPinButton');

            if (button) {
                PostPinButton.#toggle(button);
            }
        });
    }

    static async #toggle(button) {
        const post = button.closest('.Post');
        const postId = post?.dataset.postId;

        if (!postId) return;

        Working.start(button);

        try {
            const result = await Api.post('/api/pin-post', { postId: Number(postId) });

            if (!result) return;

            const words = Strings.for('PostPinButton');
            const name = result.pinned ? words.unpin : words.pin;

            button.setAttribute('aria-pressed', result.pinned ? 'true' : 'false');
            button.setAttribute('aria-label', name);
            button.setAttribute('title', name);
            button.classList.toggle('Removing', result.pinned);
            const statusWords = Strings.for('ClientStatus');
            Toast.show(result.pinned ? statusWords.pinSaved || '' : statusWords.unpinSaved || '');
        } finally {
            Working.stop(button);
        }
    }
}

ReadyHandler.add(PostPinButton.init);

    return { PostPinButton };
})();
export const PostPinButton = PostPinButtonModule.PostPinButton;

// PostShareButton.js
const PostShareButtonModule = (() => {
class PostShareButton {
    static init() {
        // Delegated on document so share buttons on dynamically added posts
        // (infinite scroll, a just-composed post) work without rebinding.
        document.addEventListener('click', async (event) => {
            const button = event.target.closest('.PostShareButton');
            if (!button) {
                return;
            }

            event.preventDefault();
            const url = button.dataset.shareUrl;
            if (!url) {
                return;
            }

            // Use the Web Share API if available (mobile / supported desktop)
            if (navigator.share) {
                try {
                    await navigator.share({
                        title: document.title,
                        url: url,
                    });
                    return;
                } catch {
                    // user cancelled or share failed – fall through to copy
                }
            }

            // Fallback: copy URL to clipboard
            try {
                await navigator.clipboard.writeText(url);
                Toast.show(Strings.for('ClientStatus').linkCopied || '');
            } catch {
                Toast.show(Strings.for('ClientStatus').linkCopyFailed || '');
            }
        });
    }
}

ReadyHandler.add(PostShareButton.init);

    return { PostShareButton };
})();
export const PostShareButton = PostShareButtonModule.PostShareButton;

// PushNotificationSetting.js
const PushNotificationSettingModule = (() => {
// PushNotificationSetting.js
/**
 * The one button that turns browser push on or off for this device. Whether
 * it is currently on is the browser's own truth (its PushManager
 * subscription), so the button reads that first and mirrors it, and every
 * change is made to the browser and then reported to the server.
 */
class PushNotificationSetting {
    static async init() {
        const button = document.querySelector('.PushSubscribeButton');
        if (!button) return;

        // Read from the same table PushNotificationSetting.php renders from,
        // so every label the script sets afterward is in whatever language a
        // reload would show, not always English.
        const words = Strings.for('PushNotificationSetting');

        // A browser without service workers or push simply can't offer this.
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            // Nothing to wait for - this browser will never offer it.
            button.disabled = true;
            button.textContent = words.unsupported || '';
            return;
        }

        const registration = await navigator.serviceWorker.ready;
        const existing = await registration.pushManager.getSubscription();

        PushNotificationSetting.#reflect(button, words, existing !== null);

        button.addEventListener('click', async () => {
            Working.start(button);

            try {
                const subscription = await registration.pushManager.getSubscription();

                if (subscription) {
                    await PushNotificationSetting.#disable(subscription);
                    PushNotificationSetting.#reflect(button, words, false);
                } else {
                    const made = await PushNotificationSetting.#enable(registration);
                    PushNotificationSetting.#reflect(button, words, made);
                }
            } finally {
                Working.stop(button);
            }
        });
    }

    static #reflect(button, words, subscribed) {
        button.textContent = (words.label || {})[subscribed ? 'on' : 'off'] || '';
        button.classList.toggle('Removing', subscribed);
    }

    static async #enable(registration) {
        if (Notification.permission === 'denied') {
            Toast.show(Strings.for('ClientStatus').notificationsBlocked || '');
            return false;
        }

        const applicationServerKey = ClientConfig.get('vapidPublicKey');
        if (!applicationServerKey) {
            Toast.show(Strings.for('ClientStatus').pushUnavailable || '');
            return false;
        }

        let subscription;
        try {
            subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey,
            });
        } catch (_) {
            Toast.show(Strings.for('ClientStatus').notificationsEnableFailed || '');
            return false;
        }

        const json = subscription.toJSON();
        const result = await Api.post('/api/push-subscribe', {
            endpoint: subscription.endpoint,
            p256dh: json.keys.p256dh,
            auth: json.keys.auth,
        });

        if (!result) {
            // The server wouldn't record it, so don't leave the browser
            // holding a subscription nothing will ever send to.
            await subscription.unsubscribe();
            return false;
        }

        Toast.show(Strings.for('ClientStatus').notificationsOn || '');
        return true;
    }

    static async #disable(subscription) {
        await Api.post('/api/push-unsubscribe', { endpoint: subscription.endpoint });
        await subscription.unsubscribe();
        Toast.show(Strings.for('ClientStatus').notificationsOff || '');
    }
}

ReadyHandler.add(PushNotificationSetting.init);

    return { PushNotificationSetting };
})();
export const PushNotificationSetting = PushNotificationSettingModule.PushNotificationSetting;

// RelayCard.js
const RelayCardModule = (() => {
/**
 * The Relays page: the form that joins one and the control on each row that
 * withdraws.
 *
 * Both are confirmed rather than immediate. Joining commits this server to
 * carrying whatever the other side publishes, and leaving stops a stream that
 * people may have been reading - neither is a small act, and neither is
 * obvious from a button.
 */
class RelayCard {
    static init() {
        document.addEventListener('submit', (event) => {
            const form = event.target.closest('.RelaySubscribeForm');
            if (!form) return;
            event.preventDefault();
            RelayCard.#subscribe(form);
        });

        document.addEventListener('click', (event) => {
            const button = event.target.closest('.RelayUnsubscribeButton');
            if (!button) return;
            RelayCard.#unsubscribe(button);
        });
    }

    static async #subscribe(form) {
        const actor_uri = form.querySelector('[name="actorURI"]').value.trim();
        const follow_object = form.querySelector('[name="followObject"]').value;

        if (actor_uri === '') return;

        const confirmed = await Dialog.confirm(
            `Subscribe to ${actor_uri}? Every public post from every other server subscribed to it will arrive here, and this server's will go out to all of them. How much that is depends entirely on those servers.`
        );

        if (!confirmed) return;

        const submit = form.querySelector('button[type="submit"]');
        Working.start(submit);

        try {
            const result = await Api.post('/api/subscribe-relay', { actorURI: actor_uri, followObject: follow_object });

            if (!result) return;

            const list = list_in(document.querySelector('.RelaysSetting'), 'RelayList');

            if (list) {
                list.prepend(list_item(RelayCard.#card(result.actorURI)));
            }

            form.querySelector('[name="actorURI"]').value = '';
        } finally {
            Working.stop(submit);
        }
    }

    static async #unsubscribe(button) {
        const actor_uri = button.dataset.actorUri;

        if (!await Dialog.confirm(`Unsubscribe from ${actor_uri}? Nothing new will arrive from it. Posts it already brought stay where they are.`)) {
            return;
        }

        Working.start(button);

        try {
            const result = await Api.post('/api/unsubscribe-relay', { actorURI: actor_uri });

            if (!result) return;

            DOMUtils.slideOut(button.closest('.RelayCard'));
        } finally {
            Working.stop(button);
        }
    }

    /** The row the server renders for a subscription that has yet to be answered. */
    static #card(actor_uri) {
        const words = Strings.for('RelayCard', { waiting: 'Waiting for the relay to accept - subscribed ' });

        const card = document.createElement('div');
        card.className = 'RelayCard';
        card.dataset.actorUri = actor_uri;

        const info = document.createElement('div');
        info.className = 'RelayCardInfo';

        const name = document.createElement('p');
        name.textContent = actor_uri;
        info.appendWithSpace(name);

        const detail = document.createElement('p');
        detail.className = 'RelayCardDetail';
        // A freshly submitted subscription always starts pending, never
        // accepted - see RelayCard.php for the counterpart of both phrasings.
        detail.appendWithSpace(document.createTextNode(words.waiting));

        const time = document.createElement('time');
        time.className = 'RelativeTime';
        time.dateTime = new Date().toISOString();
        time.textContent = Strings.for('MiscellaneousClient').justNow || '';
        detail.appendWithSpace(time);

        info.appendWithSpace(detail);
        card.appendWithSpace(info);

        const unsubscribe = document.createElement('button');
        unsubscribe.type = 'button';
        unsubscribe.className = 'Button RelayUnsubscribeButton';
        unsubscribe.dataset.actorUri = actor_uri;
        unsubscribe.textContent = Strings.for('RelayUnsubscribeButton', { name: 'Unsubscribe' }).name;
        card.appendWithSpace(unsubscribe);

        return card;
    }
}

ReadyHandler.add(RelayCard.init);

    return { RelayCard };
})();
export const RelayCard = RelayCardModule.RelayCard;

// RememberedDevice.js
const RememberedDeviceModule = (() => {
/**
 * Signing one remembered device out - a browser somebody once ticked "remember
 * me" in, which can log in as them without a password until it is revoked.
 *
 * The only place this is done. The HTMLObjects.js User component carried a second copy for a while,
 * reached from a page the device list is not on, so the one that ran was the
 * one with no confirmation and no check that the request landed.
 */
class RememberedDevice {
    static init() {
        document.addEventListener('click', async (event) => {
            const button = event.target.closest('.RememberedDeviceRevokeButton');

            if (!button || !button.dataset.tokenId) {
                return;
            }

            event.preventDefault();

            // Asked first: revoking is not undoable, and the row says only
            // where and when the device last appeared, so it is quite possible
            // to be about to sign out the phone in your own pocket.
            if (!await Dialog.confirm(Strings.for('ClientStatus').revokeDevice || '')) {
                return;
            }

            button.textContent = Strings.for('ClientStatus').revoking || '';
            Working.start(button);

            // Api.post answers null rather than throwing. Taking the card away
            // regardless said the device was signed out whether or not it was,
            // about the one thing on this page somebody is looking at because
            // they think a stranger is holding it.
            const revoked = await Api.post('/api/revoke-session', { tokenId: button.dataset.tokenId });

            if (!revoked) {
                button.textContent = Strings.for('ClientStatus').failed || '';
                Working.stop(button);

                return;
            }

            DOMUtils.slideOut(button.closest('.RememberedDevice'));
        });
    }
}

ReadyHandler.add(RememberedDevice.init);

    return { RememberedDevice };
})();
export const RememberedDevice = RememberedDeviceModule.RememberedDevice;

// RemoteFollowsForm.js
const RemoteFollowsFormModule = (() => {
// RemoteFollowsForm.js
class RemoteFollowsForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.RemoteFollowsForm');
            if (!form) return;
            event.preventDefault();
            const submit_button = form.querySelector('button[type="submit"]');
            Working.start(submit_button);
            const data = await Api.post('/api/follow-remote', {
                handles: form.querySelector('[name="handles"]').value,
            }, { form });
            Working.stop(submit_button);
            if (!data) return;
            const results = data.results || [];
            const unprocessed = data.unprocessed || [];
            const succeeded = results.filter(r => r.ok).length;
            const failed = results.filter(r => !r.ok);
            const parts = [`Followed ${succeeded} account${succeeded === 1 ? '' : 's'}.`];
            if (failed.length > 0) {
                const shown = failed.slice(0, 3).map(r => `${r.handle} (${r.error})`).join(', ');
                parts.push(`${failed.length} failed: ${shown}${failed.length > 3 ? ', …' : ''}`);
            }
            if (unprocessed.length > 0) {
                parts.push(`${unprocessed.length} not attempted yet - submit again to continue.`);
            }
            Toast.show(parts.join(' '));

            // The new follows join the list in place, pending until their
            // server accepts - the same row the server renders for one.
            const followed = results.filter(r => r.ok);

            if (followed.length > 0) {
                let list = form.querySelector('.RemoteFollowsList');

                if (!list) {
                    list = document.createElement('div');
                    list.className = 'RemoteFollowsList';
                    form.appendWithSpace(list);
                }

                for (const result of followed) {
                    const item = document.createElement('div');
                    item.className = 'RemoteFollowsItem';
                    item.appendWithSpace(document.createTextNode(result.handle));

                    // A follow just submitted is always freshly pending - the
                    // same key RemoteFollowsForm.php reads for the same word,
                    // so a follow accepted before the next reload doesn't
                    // read differently from one the server rendered.
                    const status = document.createElement('span');
                    status.className = 'RemoteFollowsStatus';
                    status.textContent = Strings.for('RemoteFollowsForm', { statusPending: 'pending' }).statusPending;
                    item.appendWithSpace(status);

                    list.appendWithSpace(item);
                }
            }

            if (failed.length === 0 && unprocessed.length === 0) {
                form.querySelector('[name="handles"]').value = '';
            }
        });
    }
}

ReadyHandler.add(RemoteFollowsForm.init);

    return { RemoteFollowsForm };
})();
export const RemoteFollowsForm = RemoteFollowsFormModule.RemoteFollowsForm;

// ReportButton.js
const ReportButtonModule = (() => {
/**
 * Every Report button on the site, delegated in one place - report buttons
 * appear on posts, profiles, and message threads, and the last of those has
 * no Post on the page to have loaded a handler.
 *
 * Reporting an encrypted message carries that one message's revealed key
 * (see HTMLObjects.js's MessageCrypto) so the server can verify and open exactly what was
 * reported - one message, never the conversation.
 */
class ReportButton {
    static init() {
        document.addEventListener('click', (event) => {
            const button = event.target.closest('.ReportButton');
            if (!button) return;
            ReportButton.#report(button);
        });
    }

    static async #report(button) {
        const words = Strings.for('ReportButton');
        const reason = await Dialog.prompt(words.prompt || '', { confirmLabel: words.name || '' });
        if (reason === null) return;

        const payload = {
            targetType: button.dataset.targetType,
            targetId: button.dataset.targetId,
            reason,
        };

        if (button.dataset.targetType === 'message' && button.closest('.Message')?.dataset.cipherEnvelope) {
            const { MessageCrypto } = await import('/scripts/HTMLObjects.js');

            // Still locked with the thread key in hand means this envelope
            // didn't open - it was encrypted under keys that have since been
            // reset, so there is no key left to reveal and nothing the server
            // could verify.
            if (button.closest('.Message').classList.contains('Locked')) {
                Toast.show(MessageCrypto.threadKey() !== null
                    ? words.unverifiable || ''
                    : words.unlockFirst || '');
                return;
            }

            payload.revealedKey = await MessageCrypto.revealKeyForMessage(button.dataset.targetId);
        }

        Working.start(button);

        try {
            const result = await Api.post('/api/report', payload);
            if (!result) return;
            button.textContent = Strings.for('ClientStatus').reported || '';
        } finally {
            Working.stop(button);
        }
    }
}

ReadyHandler.add(ReportButton.init);

    return { ReportButton };
})();
export const ReportButton = ReportButtonModule.ReportButton;

// ScrollToTop.js
const ScrollToTopModule = (() => {
class ScrollToTop {
    static #THRESHOLD = 600;

    static init() {
        // Toggle the button's visibility
        window.addEventListener('scroll', () => {
            const button = document.querySelector('.ScrollToTopButton');
            if (button) {
                button.classList.toggle('Scrolled', window.scrollY > ScrollToTop.#THRESHOLD);
            }
        });

        document.addEventListener('click', (event) => {
            const button = event.target.closest('.ScrollToTopButton');
            if (!button) return;

            // Somebody who has asked for less movement gets none of the
            // journey: straight to a pixel from the top, then that last pixel
            // scrolled. The pixel is not decoration - a list that loads as it
            // reaches its end is watching for the view to arrive, and a jump
            // that lands exactly on zero never gives it anything to notice.
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                window.scrollTo({ top: 1, behavior: 'instant' });
                window.scrollTo({ top: 0, behavior: 'smooth' });

                return;
            }

            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }
}

    return { ScrollToTop };
})();
export const ScrollToTop = ScrollToTopModule.ScrollToTop;

// Search.js
const SearchModule = (() => {
class Search {
    constructor(input, options) {
        this.input = input;
        this._resolveEndpoint = typeof options.endpoint === 'function'
            ? options.endpoint
            : () => options.endpoint;
        this.buildRequest = options.buildRequest;
        // Either the list itself or, for a list that may not be on the page at
        // all - an empty one renders only its notice - something that builds it
        // on demand. Resolved per render rather than once here, so the first
        // results are what bring the list into being.
        this._resolveContainer = typeof options.resultsContainer === 'function'
            ? options.resultsContainer
            : () => options.resultsContainer;
        this.renderItem = options.renderItem;
        this.delay = options.delay ?? 300;
        this.onBeforeFetch = options.onBeforeFetch || null;
        this._originalOnResponse = options.onResponse || null;
        this._extractItems = options.extractItems || defaultExtractItems;
        // How many results a response really holds, where the item list
        // doesn't say (help renders whole in onResponse). Null skips the
        // announcement for that response.
        this._countResults = options.countResults || null;

        this.abortController = null;
        this.debounceId = null;

        this._handleInput = this._handleInput.bind(this);
        input.addEventListener('input', this._handleInput);

        // A function resolver is NOT called here: it may build the list over
        // an empty-state notice, and construction happens on page load, when
        // that notice is exactly what should be showing. The list is built
        // when the first results arrive to go in it (_performSearch).
        this.resultsContainer = typeof options.resultsContainer === 'function' ? null : options.resultsContainer;
        this._scrollerOptions = options.enableInfiniteScroll ? options : null;

        if (options.enableInfiniteScroll && !options.countOffset) {
            throw new Error('Search: countOffset is required when enableInfiniteScroll is true');
        }

        if (this.resultsContainer) {
            this._ensureScroller();
        }
    }

    /** Binds the scroller to the container, once there is one. */
    _ensureScroller() {
        if (this.scroller || this._scrollerOptions === null || !this.resultsContainer) return;

        const options = this._scrollerOptions;

        this.scroller = InfiniteScroller.create(this.resultsContainer, {
            endpoint: () => this._resolveEndpoint(this.input.value.trim()),
            buildRequest: offset => {
                const query = this.input.value.trim();
                const req = options.buildRequest(query);
                req.offset = offset;
                return req;
            },
            countOffset: options.countOffset,
            renderItem: options.renderItem,
            active: false,
        });
    }

    trigger(queryOverride) {
        clearTimeout(this.debounceId);
        this._performSearch(queryOverride ?? this.input.value.trim());
    }

    destroy() {
        this.input.removeEventListener('input', this._handleInput);
        clearTimeout(this.debounceId);
        this.abortController?.abort();
        if (this.scroller) this.scroller.destroy();
    }

    _handleInput() {
        clearTimeout(this.debounceId);
        const query = this.input.value.trim();
        this.input.closest('.SearchBox')?.classList.toggle('HasQuery', query !== '');
        this.debounceId = setTimeout(() => {
            this._performSearch(query);
        }, this.delay);
    }

    async _performSearch(query) {
        this.abortController?.abort();
        this.abortController = new AbortController();

        if (this.onBeforeFetch) {
            this.onBeforeFetch(this.input, query);
        }

        // Quiet: a search runs on every keystroke and cancels the one before
        // it, so a failure is not worth interrupting somebody's typing over.
        const data = await Api.post(this._resolveEndpoint(query), this.buildRequest(query), {
            signal: this.abortController.signal,
            quiet: true,
        });

        if (!data) return;

        if (this.input.value.trim() !== query) return;

        this.resultsContainer = this.resultsContainer ?? this._resolveContainer();

        if (!this.resultsContainer) return;

        // The list may have only just been built; bind the scroller to it now.
        this._ensureScroller();

        this.resultsContainer.replaceChildren();

        // Extracted before the callback runs, and handed to it: every endpoint
        // names its results something slightly different (items, users, posts)
        // and a callback that guessed wrong used to throw here - after the
        // list had been emptied and before anything was rendered into it, so
        // the search simply went blank.
        const items = this._extractItems(data);

        if (this._originalOnResponse) {
            this._originalOnResponse(this.input, data, items);
        }

        items.forEach(item => {
            const el = this.renderItem(item);
            this.resultsContainer.appendWithSpace(list_item(el));
            render_math(el);
        });

        // Enable the scroller if there are more pages
        if (this.scroller && data.hasMore) {
            this.scroller.setActive(true);
        }

        this.#announce(query, this._countResults ? this._countResults(data, items) : items.length);
    }

    /**
     * Results swapping in below the box are silent to a screen reader; the
     * SearchBox's status region says how many landed. Cleared with the query,
     * so restoring the default view is not announced as a result of anything.
     */
    #announce(query, count) {
        const status = this.input.closest('.SearchBox')?.querySelector('.SearchStatus');

        if (!status || count === null) return;

        if (query === '') {
            status.textContent = '';

            return;
        }

        status.textContent = count === 1 ? '1 result' : count + ' results';
    }

    // ----------------------------------------------------------------
    // Static initialisation
    // ----------------------------------------------------------------

    static init() {
        document.addEventListener('click', (event) => {
            const clearBtn = event.target.closest('.SearchClearButton');
            if (clearBtn) {
                const input = clearBtn.closest('.SearchBox')?.querySelector('.SearchInput');
                if (input) {
                    input.value = '';
                    input.focus();
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                }
            }
        });

        Search.#initUsers();
        const posts = Search.#initPosts();
        Search.#initFriends();
        Search.#initBannedUsers();
        Search.#initHelp();

        Search.#searchFromURL(posts);
    }

    /**
     * Arriving with ?q= should actually search, not just fill the box.
     *
     * This runs after the initialisers on purpose. It used to fill the input
     * and dispatch an input event first, but the listener that would react to
     * one does not exist until #initPosts has constructed the Search - so the
     * event fired into nothing and a linked search landed on an empty page.
     * Calling trigger() also skips the debounce, which there is no reason to
     * wait out when the query arrived with the page.
     */
    static #searchFromURL(search) {
        if (search === null) {
            return;
        }

        const query = new URLSearchParams(window.location.search).get('q');

        // An empty q= is the same as no q= - searching for nothing would just
        // hide the default feed behind an empty result list.
        if (query === null || query.trim() === '') {
            return;
        }

        search.input.value = query;

        // Typing sets this from _handleInput, and it is what reveals the clear
        // button. Arriving with a query has to leave the box in the same state
        // as typing one, or there is no way to get back out of the search.
        search.input.closest('.SearchBox')?.classList.add('HasQuery');

        search.trigger();
    }

    static #initUsers() {
        const input = document.querySelector('.UserSearchInput');
        if (!input) return;
        const section = input.closest('.UserSearch').querySelector('.UserSearchSection');
        new Search(input, {
            endpoint: '/api/search-users',
            buildRequest: query => ({ q: query }),
            resultsContainer: () => list_in(section, 'UserList UserSearchList'),
            renderItem: userData => OtherUser.fromData(userData).toElement(),
            enableInfiniteScroll: true,
            countOffset: list => list.querySelectorAll('.OtherUser').length,
            onResponse: (input, data, items) => {
                const searching = input.value.trim() !== '';
                section.querySelector('h2').textContent = searching ? 'User Search Results' : 'Suggested Users';

                // Mirrors UserSearchList's two empty notices. Without it the
                // list just empties and the heading is the only thing left,
                // which reads as the page having failed rather than answered.
                if (items.length === 0) {
                    const words = Strings.for('UserSearchList');
                    const notice = document.createElement('p');
                    notice.className = 'Notice';
                    notice.textContent = searching ? words.noMatches : words.noSuggestions;
                    section.querySelector('.UserList')?.appendWithSpace(list_item(notice));
                }
            }
        });
    }

    static #initPosts() {
        const input = document.querySelector('.PostSearchInput');
        if (!input) return null;
        const container = document.querySelector('.SearchFeedList');
        return new Search(input, {
            endpoint: '/api/search-posts',
            buildRequest: query => ({
                q: query,
                userId: input.closest('.PostSearch').dataset.userId || ''
            }),
            resultsContainer: container,
            renderItem: postData => Post.fromData(postData).toElement(),
            enableInfiniteScroll: true,
            countOffset: list => list.querySelectorAll('.Post').length,
            onBeforeFetch: (input, query) => {
                const searching = query !== '';
                document.querySelector('.SearchFeedSection')?.classList.toggle('Searching', searching);
                document.querySelector('.ProfileFeedSection')?.classList.toggle('Searching', searching);
                document.querySelector('.PinnedPostSection')?.classList.toggle('Searching', searching);
            },
        });
    }

    static #initFriends() {
        const input = document.querySelector('.FriendSearchInput');
        if (!input) return;
        const container = document.querySelector('.FriendSearchList');
        new Search(input, {
            endpoint: '/api/search-friends',
            buildRequest: query => ({
                q: query,
                userId: input.closest('.FriendSearch').dataset.userId
            }),
            resultsContainer: container,
            renderItem: userData => OtherUser.fromData(userData).toElement(),
            enableInfiniteScroll: true,
            countOffset: list => list.querySelectorAll('.OtherUser').length,
            onBeforeFetch: (input, query) => {
                const searching = query !== '';
                document.querySelector('.FriendSearchSection')?.classList.toggle('Searching', searching);
                document.querySelector('.ReceivedFriendRequestSection')?.classList.toggle('Searching', searching);
                document.querySelector('.FriendSection')?.classList.toggle('Searching', searching);
                document.querySelector('.SentFriendRequestSection')?.classList.toggle('Searching', searching);
            },
        });
    }

    static #initHelp() {
        const input = document.querySelector('.HelpSearchInput');
        if (!input) return;
        const container = input.closest('.HelpSearch').querySelector('.HelpSearchResults');
        new Search(input, {
            endpoint: '/api/help-search',
            buildRequest: query => ({ q: query }),
            resultsContainer: container,
            // Help results aren't a flat item list - an empty query answers
            // with the whole browse view, grouped by category - so onResponse
            // paints everything and no items are handed back to render.
            extractItems: () => [],
            renderItem: () => null,
            countResults: data => data.grouped ? null : data.articles.length,
            onResponse: (input, data) => {
                if (data.articles.length === 0) {
                    const words = Strings.for('HelpSearch');
                    const empty = document.createElement('p');
            empty.className = 'Notice';
                    empty.textContent = words.noMatches;
                    container.appendWithSpace(empty);
                    return;
                }

                if (data.grouped) {
                    HelpSearch.renderBrowse(container, data.articles);
                } else {
                    HelpSearch.renderResults(container, data.articles);
                }
            },
        });
    }

    static #initBannedUsers() {
        const input = document.querySelector('.BannedUserSearchInput');
        if (!input) return;
        const container = document.querySelector('.BannedUserList');
        new Search(input, {
            endpoint: query => query ? '/api/search-banned-users' : '/api/banned-history',
            buildRequest: query => query ? { q: query } : {},
            resultsContainer: container,
            renderItem: data => BannedUser.fromData(data).toElement(),
            enableInfiniteScroll: true,
            countOffset: list => list.querySelectorAll('.BannedUser').length,
            onResponse: (input, data, items) => {
                if (items.length === 0) {
                    const notice = document.createElement('p');
            notice.className = 'Notice';
                    const words = Strings.for('SearchClient');
                    notice.textContent = input.value.trim() === '' ? words.noBannedUsers || '' : words.noBannedUsersMatch || '';
                    container.appendWithSpace(list_item(notice));
                }
            }
        });
    }
}

function defaultExtractItems(data) {
    const resp = data.response || data;
    return resp.items || resp.users || resp.posts || resp.articles || [];
}

ReadyHandler.add(Search.init);

    return { Search };
})();
export const Search = SearchModule.Search;

// SensitiveMediaSetting.js
const SensitiveMediaSettingModule = (() => {
/**
 * Client twin of SensitiveMediaSetting.php: saves the reader's answer as they
 * give it.
 *
 * The box is left showing what they chose even while the request is in flight -
 * a checkbox that springs back and then settles reads as a fault. If the save
 * fails, Api has already told them so.
 */
class SensitiveMediaSetting {
    static init() {
        document.addEventListener('change', async (event) => {
            const input = event.target.closest('.SensitiveMediaSetting input[name="showSensitiveMedia"]');

            if (!input) return;

            await Api.post('/api/update-sensitive-media', { showSensitiveMedia: input.checked });
        });
    }
}

ReadyHandler.add(SensitiveMediaSetting.init);

    return { SensitiveMediaSetting };
})();
export const SensitiveMediaSetting = SensitiveMediaSettingModule.SensitiveMediaSetting;

// SignupForm.js
const SignupFormModule = (() => {
// SignupForm.js
class SignupForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.SignupForm');
            if (!form) return;
            event.preventDefault();
            const submit_button = form.querySelector('button[type="submit"]');
            Working.start(submit_button);
            const captcha_input = form.querySelector('[name="cf-turnstile-response"]');
            const data = await Api.post('/api/signup', {
                username: form.querySelector('[name="username"]').value,
                email: form.querySelector('[name="email"]').value,
                displayName: form.querySelector('[name="displayName"]').value,
                description: form.querySelector('[name="description"]').value,
                password: form.querySelector('[name="password"]').value,
                rememberMe: form.querySelector('[name="rememberMe"]').checked,
                captchaToken: captcha_input ? captcha_input.value : null,
            }, { form });
            if (!data) {
                Working.stop(submit_button);
                return;
            }
            window.location = ClientConfig.siteURL() + (data.verified ? '/' : '/check-inbox');
        });
    }
}

ReadyHandler.add(SignupForm.init);

    return { SignupForm };
})();
export const SignupForm = SignupFormModule.SignupForm;

// SiteInfoSettingsForm.js
const SiteInfoSettingsFormModule = (() => {
class SiteInfoSettingsForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.SiteInfoSettingsForm');
            if (!form) return;
            event.preventDefault();
            const submit_button = form.querySelector('button[type="submit"]');
            Working.start(submit_button);
            const field = form.querySelector('textarea');
            const field_name = field.name;
            const path = '/api/' + field_name.replace(/Text$/, '') + '-settings';
            const data = await Api.post(path, { [field_name]: field.value });
            Working.stop(submit_button);
            if (data) Toast.show(Strings.for('ClientStatus').settingsSaved || '');
        });
    }
}

ReadyHandler.add(SiteInfoSettingsForm.init);

    return { SiteInfoSettingsForm };
})();
export const SiteInfoSettingsForm = SiteInfoSettingsFormModule.SiteInfoSettingsForm;

// SpectrumAnalyser.js
const SpectrumAnalyserModule = (() => {
/**
 * The moving spectrum above an audio player.
 *
 * Taps the graph the browser is already decoding for playback rather than
 * decoding the file itself, so it costs one FFT window of memory and starts
 * instantly. It shows the present instant and nothing ahead of the playhead,
 * because nothing ahead of the playhead has been decoded.
 */
class SpectrumAnalyser {
    /** 1024 samples in, 512 bins out - enough shape to read, cheap to draw. */
    static FFT_SIZE = 1024;

    /** How many bars the bins are grouped into. */
    static BARS = 48;

    /**
     * How much of the spectrum to draw. The top of it is empty on nearly all
     * recorded sound, and drawing it wastes most of the width on a flat line.
     */
    static USED_BINS = 0.55;

    /**
     * Barely any smoothing in the analyser itself. Its own smoothing is
     * symmetric - it slows the fall as much as the rise - so held loud sound
     * pins every bar to the ceiling and the display stops saying anything.
     * The fall is shaped below instead.
     */
    static SMOOTHING = 0.2;

    /**
     * How much of its height a bar keeps each frame while the sound under it
     * is quieter than it was. Rises are taken immediately, so a bar snaps up
     * and drops away - which is what makes a sustained peak still read as one
     * rather than as a flat line.
     */
    static DECAY = 0.78;

    /**
     * One graph per audio element, because createMediaElementSource may only
     * be called once for an element - a second call throws, and the element is
     * left silent.
     */
    static #graphs = new WeakMap();

    static init() {
        // play does not bubble, so this listens on the way down instead. It is
        // also the user gesture an AudioContext needs to start.
        document.addEventListener('play', (event) => {
            const audio = event.target;

            if (!(audio instanceof HTMLMediaElement) || !audio.classList.contains('Audio')) return;

            const canvas = audio.parentElement?.querySelector('.SpectrumAnalyser');

            if (!canvas) return;

            // Somebody who has asked for less movement gets the player and no
            // dancing bars.
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

            SpectrumAnalyser.#start(audio, canvas);
        }, true);
    }

    static #graphFor(audio) {
        const existing = SpectrumAnalyser.#graphs.get(audio);

        if (existing) return existing;

        const AudioContextClass = window.AudioContext || window.webkitAudioContext;

        if (!AudioContextClass) return null;

        const context = new AudioContextClass();
        const analyser = context.createAnalyser();
        analyser.fftSize = SpectrumAnalyser.FFT_SIZE;
        analyser.smoothingTimeConstant = SpectrumAnalyser.SMOOTHING;

        // Through the analyser AND on to the speakers: a source routed into a
        // node that goes nowhere plays silently.
        context.createMediaElementSource(audio).connect(analyser);
        analyser.connect(context.destination);

        const graph = {
            context,
            analyser,
            bins: new Uint8Array(analyser.frequencyBinCount),
            // What each bar is showing, carried between frames so the fall can
            // be shaped. Rises replace it outright.
            heights: new Float32Array(SpectrumAnalyser.BARS),
            drawing: false,
        };
        SpectrumAnalyser.#graphs.set(audio, graph);

        return graph;
    }

    static #start(audio, canvas) {
        const graph = SpectrumAnalyser.#graphFor(audio);

        if (!graph || graph.drawing) return;

        // A context created before the gesture starts suspended; a second play
        // after a pause finds it suspended again.
        if (graph.context.state === 'suspended') graph.context.resume();

        graph.drawing = true;

        const surface = canvas.getContext('2d');
        const ratio = window.devicePixelRatio || 1;

        // Drawn at the display's own resolution, so the bars have hard edges
        // rather than being scaled up from a smaller buffer.
        canvas.width = canvas.clientWidth * ratio || canvas.width;
        canvas.height = canvas.clientHeight * ratio || canvas.height;

        const colour = getComputedStyle(canvas).color;

        const draw = () => {
            if (audio.paused || audio.ended) {
                graph.drawing = false;
                surface.clearRect(0, 0, canvas.width, canvas.height);

                return;
            }

            graph.analyser.getByteFrequencyData(graph.bins);
            surface.clearRect(0, 0, canvas.width, canvas.height);
            surface.fillStyle = colour;

            const used = Math.floor(graph.bins.length * SpectrumAnalyser.USED_BINS);
            const perBar = Math.max(1, Math.floor(used / SpectrumAnalyser.BARS));
            const barWidth = canvas.width / SpectrumAnalyser.BARS;

            for (let bar = 0; bar < SpectrumAnalyser.BARS; bar++) {
                let total = 0;

                for (let offset = 0; offset < perBar; offset++) {
                    total += graph.bins[bar * perBar + offset] || 0;
                }

                const level = total / perBar / 255;

                // Straight up, gently down.
                graph.heights[bar] = level > graph.heights[bar]
                    ? level
                    : graph.heights[bar] * SpectrumAnalyser.DECAY;

                const height = graph.heights[bar] * canvas.height;

                surface.fillRect(
                    bar * barWidth,
                    canvas.height - height,
                    Math.max(1, barWidth - ratio),
                    height
                );
            }

            requestAnimationFrame(draw);
        };

        requestAnimationFrame(draw);
    }
}

ReadyHandler.add(SpectrumAnalyser.init);

    return { SpectrumAnalyser };
})();
export const SpectrumAnalyser = SpectrumAnalyserModule.SpectrumAnalyser;

// StagedPostCard.js
const StagedPostCardModule = (() => {
// StagedPostCard.js
/**
 * The controls on a draft or scheduled post: publish it now, open it for
 * editing, or discard it for good. Publishing and discarding take the card
 * with them; editing is a link to the composer holding it, a page of its own
 * built from what the server saved.
 */
class StagedPostCard {
    static init() {
        document.addEventListener('click', async (event) => {
            const publish = event.target.closest('.StagedPostPublishButton');
            if (publish) {
                await StagedPostCard.#act(publish, '/api/publish-staged', Strings.for('StagedPostClient').published || '');
                return;
            }

            const discard = event.target.closest('.StagedPostDiscardButton');
            if (discard) {
                if (!await Dialog.confirm(Strings.for('StagedPostClient').discardConfirm || '')) {
                    return;
                }

                await StagedPostCard.#act(discard, '/api/discard-staged', Strings.for('StagedPostClient').discarded || '');
            }
        });
    }

    /** Mirrors StagedPostCard.php, rebuilt from what the server saved. */
    static #card(data) {
        const card = document.createElement('div');
        card.className = 'Card StagedPostCard';
        card.setAttribute('data-staged-post-id', data.stagedPostId);
        card.setAttribute('data-title', data.title || '');
        card.setAttribute('data-description-delta', data.descriptionDelta || '');
        card.setAttribute('data-link-url', data.linkURL || '');

        if (data.publishAtEpoch) {
            card.setAttribute('data-publish-at-epoch', data.publishAtEpoch);
        }

        if (data.latitude !== null && data.latitude !== undefined) {
            card.setAttribute('data-latitude', data.latitude);
            card.setAttribute('data-longitude', data.longitude);
        }

        if (data.title) {
            const title = document.createElement('p');
            title.className = 'StagedPostTitle';
            title.textContent = data.title;
            card.appendWithSpace(title);
        }

        if (data.description) {
            const body = document.createElement('p');
            body.textContent = data.description.length > 200 ? data.description.slice(0, 200) + '…' : data.description;
            card.appendWithSpace(body);
        }

        if (data.linkURL) {
            const link = document.createElement('p');
        link.className = 'StagedPostLink';
            link.textContent = data.linkURL;
            card.appendWithSpace(link);
        }

        const when_words = Strings.for('StagedPostWhen', {
            scheduled: 'Scheduled for {when}',
            draft: 'Draft - publishes only when you say so',
        });

        const when = document.createElement('p');
        when.className = 'StagedPostWhen';
        when.textContent = data.publishAt !== null
            ? when_words.scheduled.replace('{when}', data.publishAt)
            : when_words.draft;
        card.appendWithSpace(when);

        const actions = document.createElement('div');
        actions.className = 'StagedPostActions';

        for (const [className, label] of [
            ['StagedPostPublishButton', Strings.for('StagedPostPublishButton', { name: 'Publish Now' }).name],
            ['StagedPostDiscardButton Removing', Strings.for('StagedPostDiscardButton', { name: 'Discard' }).name],
        ]) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'Button ' + className;
            button.textContent = label;
            actions.appendWithSpace(button);
        }

        // A link, mirroring StagedPostEditButton.php: editing is a page of its
        // own, holding the composer this was written in.
        const edit = document.createElement('a');
        edit.className = 'Button StagedPostEditButton';
        edit.href = ClientConfig.siteURL() + '/drafts/' + data.stagedPostId;
        edit.textContent = Strings.for('StagedPostClient').edit || '';
        actions.appendWithSpace(edit);

        card.appendWithSpace(actions);

        return card;
    }

    static async #act(button, endpoint, done) {
        const card = button.closest('.StagedPostCard');
        Working.start(button);

        try {
            const result = await Api.post(endpoint, {
                stagedPostId: Number(card.dataset.stagedPostId),
            });

            if (!result) return;

            Toast.show(done);
            DOMUtils.slideOut(card);
        } finally {
            Working.stop(button);
        }
    }
}

ReadyHandler.add(StagedPostCard.init);

    return { StagedPostCard };
})();
export const StagedPostCard = StagedPostCardModule.StagedPostCard;

// ThemeSelect.js
const ThemeSelectModule = (() => {
// ThemeSelect.js
class ThemeSelect {
    static init() {
        document.addEventListener('change', async (event) => {
            const select = event.target.closest('.ThemeSelect');
            if (!select) return;
            const theme = select.value;
            const previous_theme = document.documentElement.dataset.theme || 'system';
            const apply = (value) => {
                if (value === 'system') delete document.documentElement.dataset.theme;
                else document.documentElement.dataset.theme = value;
                sync_theme_color();
            };
            apply(theme);
            if (await Api.post('/api/update-theme', { theme }) === null) {
                apply(previous_theme);
                select.value = previous_theme;
            }
        });
    }
}

ReadyHandler.add(ThemeSelect.init);

    return { ThemeSelect };
})();
export const ThemeSelect = ThemeSelectModule.ThemeSelect;

// TopicSummaryCard.js
const TopicSummaryCardModule = (() => {
/**
 * Client twin of TopicSummaryCard.php: asks for a topic's paragraph when the
 * page arrived without one, and puts it in.
 *
 * The timer works down the topics by how much they are being talked about, so
 * the busy ones are written before anybody opens them. Somewhere down that
 * list is a topic nobody has asked about yet, and the person opening its page
 * is the first to want one - so it is written for them while they read, rather
 * than the page saying nothing and staying that way.
 *
 * Quiet on failure. Nobody asked for this paragraph, and a page that simply
 * has no summary is the ordinary state of one - a toast would report a fault
 * to somebody who was not waiting on anything.
 */
class TopicSummaryCard {
    static async init() {
        const card = document.querySelector('.TopicSummaryCard.Awaited');

        if (!card) return;

        const summary = await Api.post('/api/topic-summary', {
            type: card.dataset.topicType,
            slug: card.dataset.topicSlug,
        }, { quiet: true });

        if (!summary?.summary) return;

        TopicSummaryCard.fill(card, summary.summary);
    }

    /** The same two elements TopicSummaryCard.php builds, in the same order. */
    static fill(card, text) {
        const paragraph = document.createElement('p');
        paragraph.textContent = text;

        const label = document.createElement('p');
        label.className = 'TopicSummaryLabel';
        label.textContent = Strings.for('TopicSummaryCard', { label: 'AI-generated summary' }).label;

        card.appendWithSpace(paragraph);
        card.appendWithSpace(label);
        card.classList.remove('Awaited');
    }
}

ReadyHandler.add(TopicSummaryCard.init);

    return { TopicSummaryCard };
})();
export const TopicSummaryCard = TopicSummaryCardModule.TopicSummaryCard;

// TwoFactorForm.js
const TwoFactorFormModule = (() => {
// TwoFactorForm.js
class TwoFactorForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.TwoFactorForm');
            if (!form) return;
            event.preventDefault();
            const submit_button = form.querySelector('button[type="submit"]');
            Working.start(submit_button);
            const data = await Api.post('/api/verify-2fa', {
                code: form.querySelector('[name="code"]').value,
            }, { form });
            if (!data) {
                Working.stop(submit_button);
                return;
            }
            window.location = ClientConfig.siteURL() + '/';
        });
    }
}

ReadyHandler.add(TwoFactorForm.init);

    return { TwoFactorForm };
})();
export const TwoFactorForm = TwoFactorFormModule.TwoFactorForm;

// TwoFactorSettingsForm.js
const TwoFactorSettingsFormModule = (() => {
class TwoFactorSettingsForm {
    static init() {
        // Read from the same table TwoFactorSettingsForm.php renders from, so
        // toggling shows the same words a reload would - in whatever language
        // that is, not just English.
        const words = Strings.for('TwoFactorSettingsForm');
        const pick = (entry, state) => (entry || {})[state] || '';

        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.TwoFactorSettingsForm');
            if (!form) return;
            event.preventDefault();

            const existing_error = form.querySelector('.Error');
            if (existing_error) existing_error.remove();

            // The form carries two submit buttons while 2FA is on (the toggle
            // and the recovery-code regenerate), so the clicked one decides
            // the action.
            const toggle_button = form.querySelector('button[type="submit"]');
            const submit_button = event.submitter && event.submitter.dataset.action
                ? event.submitter
                : toggle_button;
            const password_input = form.querySelector('[name="currentPassword"]');
            Working.start(submit_button);

            const data = await Api.post('/api/two-factor', {
                action: submit_button.dataset.action,
                currentPassword: password_input.value,
            }, { form });

            if (!data) {
                Working.stop(submit_button);
                return;
            }

            password_input.value = '';

            if (data.recoveryCodes) {
                TwoFactorSettingsForm.#showRecoveryCodes(form, data.recoveryCodes, words);
            }

            if (submit_button.dataset.action === 'regenerate-recovery') {
                Working.stop(submit_button);
                Toast.show(pick(words.toast, 'regenerated'));
                return;
            }

            const now_enabled = data.enabled;
            const state = now_enabled ? 'on' : 'off';
            form.querySelector('legend').textContent = pick(words.legend, state);
            form.querySelector('fieldset p').textContent = pick(words.explanation, state);
            toggle_button.textContent = pick(words.submit, state);
            toggle_button.dataset.action = now_enabled ? 'disable' : 'enable';

            // The regenerate button and any shown codes exist only while 2FA
            // is on, mirroring what a reload would render.
            const regenerate_button = form.querySelector('[data-action="regenerate-recovery"]');

            if (now_enabled && !regenerate_button) {
                const regenerate = document.createElement('button');
                regenerate.className = 'Button SubmitButton';
                regenerate.type = 'submit';
                regenerate.dataset.action = 'regenerate-recovery';
                regenerate.textContent = words.regenerate || '';
                form.appendWithSpace(regenerate);
            } else if (!now_enabled) {
                if (regenerate_button) regenerate_button.remove();
                const codes_block = form.querySelector('.RecoveryCodes');
                if (codes_block) codes_block.remove();
            }

            Working.stop(submit_button);

            Toast.show(pick(words.toast, state));
        });
    }

    // The one time the codes exist in plain text is the response that issued
    // them, so this is the one place they're ever shown.
    static #showRecoveryCodes(form, codes, words) {
        const existing = form.querySelector('.RecoveryCodes');
        if (existing) existing.remove();

        const block = document.createElement('div');
        block.className = 'RecoveryCodes';

        const heading = document.createElement('p');
        heading.textContent = words.recoveryHeading || '';
        block.appendWithSpace(heading);

        const note = document.createElement('p');
        note.textContent = words.recoveryNote || '';
        block.appendWithSpace(note);

        const list = document.createElement('ul');

        for (const code of codes) {
            const item = document.createElement('li');
            const code_element = document.createElement('code');
            code_element.textContent = code;
            item.appendWithSpace(code_element);
            list.appendWithSpace(item);
        }

        block.appendWithSpace(list);
        form.querySelector('fieldset').after(block);
    }
}

ReadyHandler.add(TwoFactorSettingsForm.init);

    return { TwoFactorSettingsForm };
})();
export const TwoFactorSettingsForm = TwoFactorSettingsFormModule.TwoFactorSettingsForm;

// UsernameValidation.js
const UsernameValidationModule = (() => {
// UsernameValidation.js
class UsernameValidation {
    // The pending availability-check timer per input - the handler is delegated
    // on document, and a timer id is this module's own bookkeeping.
    static #debounceIds = new WeakMap();

    static init() {
        const sanitize = (input) => {
            input.value = input.value.toLowerCase().replace(/[^a-z0-9_]/g, '').slice(0, 32);
        };

        document.addEventListener('input', (event) => {
            const input = event.target.closest('.SignupForm [name="username"]');
            if (!input) return;
            sanitize(input);
            UsernameValidation.#checkAvailability(input);
        });

        document.addEventListener('change', (event) => {
            const input = event.target.closest('.SignupForm [name="username"]');
            if (!input) return;
            sanitize(input);
        });
    }

    static #checkAvailability(input) {
        const status = input.closest('.SignupForm').querySelector('.UsernameAvailability');
        if (!status) return;
        clearTimeout(UsernameValidation.#debounceIds.get(input));
        const requested = input.value;
        if (requested === '') {
            status.textContent = '';
            status.classList.remove('Error');
            return;
        }
        const debounce_id = setTimeout(async () => {
            input.availabilityAbortController?.abort();
            const controller = new AbortController();
            input.availabilityAbortController = controller;
            // Quiet: this asks again on every keystroke and cancels the one
            // before it, so a failure just leaves the hint blank.
            const data = await Api.post('/api/username-available', { username: requested }, {
                signal: controller.signal,
                quiet: true,
            });

            if (!data) return;
            if (input.value !== requested) return;

            status.classList.toggle('Error', !data.available);
            status.textContent = data.available
                ? `${data.username} is available.`
                : `${data.username} is already taken.`;
        }, 300);
        UsernameValidation.#debounceIds.set(input, debounce_id);
    }
}

ReadyHandler.add(UsernameValidation.init);

    return { UsernameValidation };
})();
export const UsernameValidation = UsernameValidationModule.UsernameValidation;

// VideoCall.js
const VideoCallModule = (() => {
/**
 * One-to-one video calling inside a message thread.
 *
 * Nothing is offered until a direct path between the two browsers has been
 * proven, because there is no relay to fall back on: while both people have the
 * thread open, one side quietly opens a data-channel-only connection to the
 * other. That needs no camera and shows nothing, and only if it connects does a
 * call button appear.
 *
 * Candidates are gathered to completion before an offer is sent rather than
 * trickled, so one message each way sets up a call and the server relays a
 * handful of requests instead of a stream of them.
 *
 * Presence decides whether a call can be STARTED. It has no say once one is
 * running: the call's own connection is the liveness signal, and leaving the
 * page tears it down, which the other side sees directly.
 */
class VideoCall {
    /** Comfortably inside ChatPresence::PRESENCE_SECONDS, so one lost request is survivable. */
    static #PRESENCE_INTERVAL_MS = 10000;

    /** How long to wait for ICE gathering before giving up on a negotiation. */
    static #GATHER_TIMEOUT_MS = 5000;

    /**
     * How long a probe may stay unresolved before it is abandoned. ICE reaches
     * 'failed' on its own eventually, but not always promptly and not from every
     * state, and a probe left open blocks every later attempt - so there is a
     * clock on it rather than a wait for the browser to give up.
     */
    static #PROBE_TIMEOUT_MS = 15000;

    static #otherUserId = null;
    static #list = null;
    static #composer = null;
    static #presenceTimer = null;

    /** Set once a data-channel probe has actually connected these two browsers. */
    static #pathProven = false;
    static #probe = null;
    static #probeTimer = null;

    static #connection = null;
    static #localStream = null;
    static #stage = null;
    static #panel = null;
    static #callButton = null;
    static #offer = null;

    static init() {
        // The composer, not the list: a thread nobody has written in yet has no
        // list at all, and a call is exactly the thing you might want there.
        VideoCall.#composer = document.querySelector('.MessageComposer[data-other-user-id]');

        if (!VideoCall.#composer || ClientConfig.get('currentUserId') === null) {
            return;
        }

        VideoCall.#otherUserId = Number(VideoCall.#composer.dataset.otherUserId);

        document.addEventListener('ws:call', (event) => VideoCall.#receive(event.detail));
        document.addEventListener('click', (event) => VideoCall.#onClick(event));

        // Leaving ends any call outright, and drops the heartbeat so the other
        // side stops being offered a call to someone who has gone.
        window.addEventListener('pagehide', () => {
            VideoCall.#hangUp(false);
            VideoCall.#endProbe();
            VideoCall.#post('/api/chat-presence', { otherUserId: VideoCall.#otherUserId, leaving: true });
        });

        VideoCall.#beat();
        VideoCall.#presenceTimer = setInterval(() => VideoCall.#beat(), VideoCall.#PRESENCE_INTERVAL_MS);
    }

    // ----------------------------------------------------------------
    // Presence, and the silent probe it triggers
    // ----------------------------------------------------------------

    static async #beat() {
        const result = await VideoCall.#post('/api/chat-presence', { otherUserId: VideoCall.#otherUserId });

        if (result === null) {
            return;
        }

        if (!result.otherUserPresent) {
            VideoCall.#showCallButton(false);

            return;
        }

        if (VideoCall.#pathProven) {
            VideoCall.#showCallButton(true);
        } else if (VideoCall.#probe === null && VideoCall.#initiates()) {
            VideoCall.#openProbe();
        }
    }

    /**
     * Both browsers run this same code, so without a rule they would both offer
     * a probe and neither answer. The lower id going first is arbitrary but
     * agreed - it mirrors VideoCall::initiates() on the server.
     */
    static #initiates() {
        return Number(ClientConfig.get('currentUserId')) < VideoCall.#otherUserId;
    }

    static #peerConnection() {
        return new RTCPeerConnection({ iceServers: JSON.parse(VideoCall.#composer.dataset.iceServers ?? '[]') ?? [] });
    }

    /** The probe carries no media at all - it exists only to prove a path. */
    static async #openProbe() {
        VideoCall.#probe = VideoCall.#peerConnection();
        VideoCall.#probe.createDataChannel('path');
        VideoCall.#watchProbe(VideoCall.#probe);

        const offer = await VideoCall.#describe(VideoCall.#probe, () => VideoCall.#probe.createOffer());

        VideoCall.#post('/api/call-signal', { otherUserId: VideoCall.#otherUserId, type: 'probeOffer', signal: offer });
    }

    static async #answerProbe(offer) {
        VideoCall.#probe = VideoCall.#peerConnection();
        VideoCall.#watchProbe(VideoCall.#probe);

        await VideoCall.#probe.setRemoteDescription(offer);
        const answer = await VideoCall.#describe(VideoCall.#probe, () => VideoCall.#probe.createAnswer());

        VideoCall.#post('/api/call-signal', { otherUserId: VideoCall.#otherUserId, type: 'probeAnswer', signal: answer });
    }

    /**
     * A probe that connects has answered its only question, so it is closed
     * again straight away rather than held open for the life of the page.
     *
     * Every other way it can end has to close it too. #beat() will not open a
     * second probe while one is open, so a probe that stalls - in 'checking'
     * forever, or dropped into 'disconnected' - would otherwise mean the call
     * button never appears again for the life of the page, even once a path
     * becomes available.
     */
    static #watchProbe(connection) {
        VideoCall.#probeTimer = setTimeout(() => VideoCall.#endProbe(), VideoCall.#PROBE_TIMEOUT_MS);

        connection.onconnectionstatechange = () => {
            if (connection.connectionState === 'connected') {
                VideoCall.#pathProven = true;
                VideoCall.#showCallButton(true);
                VideoCall.#endProbe();
            } else if (['failed', 'disconnected', 'closed'].includes(connection.connectionState)) {
                // No direct path, and there is no relay by design - so no call
                // is offered rather than one being proxied. The next beat tries
                // again, in case the network has changed since.
                VideoCall.#endProbe();
            }
        };
    }

    /** Closes any open probe and frees the slot, so the next beat can retry. */
    static #endProbe() {
        clearTimeout(VideoCall.#probeTimer);
        VideoCall.#probeTimer = null;

        // Cleared before closing: close() raises 'closed', which lands back
        // here, and this way that second pass has nothing left to do.
        const probe = VideoCall.#probe;
        VideoCall.#probe = null;
        probe?.close();
    }

    /**
     * Applies a description and waits for gathering to finish, so the result
     * carries every candidate and no follow-up messages are needed.
     */
    static async #describe(connection, create) {
        await connection.setLocalDescription(await create());

        if (connection.iceGatheringState !== 'complete') {
            await new Promise((resolve) => {
                const done = () => {
                    if (connection.iceGatheringState === 'complete') {
                        connection.removeEventListener('icegatheringstatechange', done);
                        resolve();
                    }
                };

                connection.addEventListener('icegatheringstatechange', done);
                setTimeout(resolve, VideoCall.#GATHER_TIMEOUT_MS);
            });
        }

        return connection.localDescription;
    }

    // ----------------------------------------------------------------
    // The call
    // ----------------------------------------------------------------

    static async #call() {
        if (!await VideoCall.#openCamera()) {
            return;
        }

        const words = Strings.for('VideoCall');
        VideoCall.#showStage(words.calling || '', words.cancel || '');
        VideoCall.#connection = VideoCall.#buildCall();

        const offer = await VideoCall.#describe(VideoCall.#connection, () => VideoCall.#connection.createOffer());

        VideoCall.#post('/api/call-signal', { otherUserId: VideoCall.#otherUserId, type: 'offer', signal: offer });
    }

    static async #accept() {
        if (!await VideoCall.#openCamera()) {
            VideoCall.#post('/api/call-signal', { otherUserId: VideoCall.#otherUserId, type: 'decline', signal: null });

            return;
        }

        const words = Strings.for('VideoCall');
        VideoCall.#showStage(words.connecting || '', words.end || '');
        VideoCall.#connection = VideoCall.#buildCall();

        await VideoCall.#connection.setRemoteDescription(VideoCall.#offer);
        VideoCall.#offer = null;

        const answer = await VideoCall.#describe(VideoCall.#connection, () => VideoCall.#connection.createAnswer());

        VideoCall.#post('/api/call-signal', { otherUserId: VideoCall.#otherUserId, type: 'answer', signal: answer });
    }

    static #buildCall() {
        const connection = VideoCall.#peerConnection();

        VideoCall.#localStream.getTracks().forEach((track) => connection.addTrack(track, VideoCall.#localStream));

        connection.ontrack = (event) => {
            VideoCall.#stage.querySelector('.VideoCallRemote').srcObject = event.streams[0];
        };

        connection.onconnectionstatechange = () => {
            if (connection.connectionState === 'connected') {
                const words = Strings.for('VideoCall');
                VideoCall.#setStatus(words.connected || '');
                VideoCall.#setEndLabel(words.end || '');
            } else if (['failed', 'disconnected', 'closed'].includes(connection.connectionState)) {
                // Includes the other person simply leaving the page - their side
                // goes away and this is how it is noticed.
                VideoCall.#hangUp(false);
            }
        };

        return connection;
    }

    static async #openCamera() {
        try {
            VideoCall.#localStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });

            return true;
        } catch (error) {
            Toast.show(Strings.for('VideoCall').permissionError || '');

            return false;
        }
    }

    /**
     * Ends whatever is running and puts the thread back. Tells the other side
     * unless this was triggered BY the other side, or by the page going away.
     */
    static #hangUp(announce = true) {
        if (announce && (VideoCall.#connection !== null || VideoCall.#offer !== null)) {
            VideoCall.#post('/api/call-signal', { otherUserId: VideoCall.#otherUserId, type: 'hangup', signal: null });
        }

        VideoCall.#connection?.close();
        VideoCall.#connection = null;
        VideoCall.#offer = null;

        VideoCall.#localStream?.getTracks().forEach((track) => track.stop());
        VideoCall.#localStream = null;

        VideoCall.#hideStage();
    }

    // ----------------------------------------------------------------
    // Signals in
    // ----------------------------------------------------------------

    static async #receive(call) {
        if (Number(call.fromUserId) !== VideoCall.#otherUserId) {
            return;
        }

        if (call.type === 'probeOffer') {
            VideoCall.#answerProbe(call.signal);
        } else if (call.type === 'probeAnswer') {
            VideoCall.#probe?.setRemoteDescription(call.signal);
        } else if (call.type === 'offer') {
            // Both pressed Video call inside the same moment, so each is holding
            // an attempt and being offered another. The rule that settles who
            // opens the probe settles this too: the initiator keeps its own
            // attempt and ignores the incoming one, the other drops its attempt
            // and takes theirs. Without it both sides sit in 'Calling…' waiting
            // for an answer neither will send.
            if (VideoCall.#connection !== null || VideoCall.#offer !== null) {
                if (VideoCall.#initiates()) {
                    return;
                }

                VideoCall.#hangUp(false);
            }

            VideoCall.#offer = call.signal;
            VideoCall.#showIncoming();
        } else if (call.type === 'answer') {
            VideoCall.#connection?.setRemoteDescription(call.signal);
        } else if (call.type === 'decline') {
            Toast.show(Strings.for('VideoCall').declined || '');
            VideoCall.#hangUp(false);
        } else if (call.type === 'hangup') {
            VideoCall.#hangUp(false);
        }
    }

    // ----------------------------------------------------------------
    // What the thread looks like during all this
    // ----------------------------------------------------------------

    static #onClick(event) {
        if (event.target.closest('.VideoCallStartButton')) {
            VideoCall.#call();
        } else if (event.target.closest('.VideoCallAcceptButton')) {
            VideoCall.#accept();
        } else if (event.target.closest('.VideoCallDeclineButton')) {
            VideoCall.#post('/api/call-signal', { otherUserId: VideoCall.#otherUserId, type: 'decline', signal: null });
            VideoCall.#offer = null;
            VideoCall.#hideStage();
        } else if (event.target.closest('.VideoCallEndButton')) {
            VideoCall.#hangUp();
        }
    }

    static #showCallButton(show) {
        if (!show) {
            VideoCall.#callButton?.remove();
            VideoCall.#callButton = null;

            return;
        }

        if (VideoCall.#callButton !== null || VideoCall.#composer === null || VideoCall.#connection !== null) {
            return;
        }

        VideoCall.#callButton = document.createElement('button');
        VideoCall.#callButton.type = 'button';
        VideoCall.#callButton.className = 'VideoCallStartButton Button';
        VideoCall.#callButton.textContent = '📹 ' + (Strings.for('VideoCall').call || '');
        VideoCall.#composer.appendWithSpace(VideoCall.#callButton);
    }

    /** The call takes the thread's place - the messages are still there behind it. */
    static #showStage(status, end_label) {
        VideoCall.#showCallButton(false);
        VideoCall.#stopBeating();

        VideoCall.#stage = document.createElement('div');
        VideoCall.#stage.className = 'VideoCallStage';

        const remote = document.createElement('video');
        remote.className = 'VideoCallRemote';
        remote.autoplay = true;
        remote.playsInline = true;
        VideoCall.#stage.appendWithSpace(remote);

        const local = document.createElement('video');
        local.className = 'VideoCallLocal';
        local.autoplay = true;
        local.playsInline = true;
        local.muted = true;
        local.srcObject = VideoCall.#localStream;
        VideoCall.#stage.appendWithSpace(local);

        // A thread with nothing in it has no list to stand aside, so the stage
        // takes its place directly above the composer.
        VideoCall.#list = document.querySelector('.MessageList');

        if (VideoCall.#list !== null) {
            VideoCall.#list.hidden = true;
            VideoCall.#list.after(VideoCall.#stage);
        } else {
            VideoCall.#composer.before(VideoCall.#stage);
        }

        VideoCall.#showPanel(status, end_label);
    }

    /** The composer's place is taken by what the call is doing, and a way out. */
    static #showPanel(status, end_label) {
        VideoCall.#panel?.remove();

        VideoCall.#panel = document.createElement('div');
        VideoCall.#panel.className = 'VideoCallPanel';

        const text = document.createElement('span');
        text.className = 'VideoCallStatus';
        text.textContent = status;
        VideoCall.#panel.appendWithSpace(text);

        if (end_label !== null) {
            const end = document.createElement('button');
            end.type = 'button';
        end.className = 'VideoCallEndButton Button';
            end.textContent = end_label;
            VideoCall.#panel.appendWithSpace(end);
        }

        if (VideoCall.#composer !== null) {
            VideoCall.#composer.hidden = true;
            VideoCall.#composer.after(VideoCall.#panel);
        }
    }

    /** An offer arriving is the one case with no stage yet - just a choice. */
    static #showIncoming() {
        VideoCall.#showCallButton(false);
        VideoCall.#showPanel(Strings.for('VideoCall').incoming || '', null);

        const accept = document.createElement('button');
        accept.type = 'button';
        accept.className = 'VideoCallAcceptButton Button';
        accept.textContent = Strings.for('VideoCall').accept || '';
        VideoCall.#panel.appendWithSpace(accept);

        const decline = document.createElement('button');
        decline.type = 'button';
        decline.className = 'VideoCallDeclineButton Button';
        decline.textContent = Strings.for('VideoCall').decline || '';
        VideoCall.#panel.appendWithSpace(decline);
    }

    static #setStatus(text) {
        const status = VideoCall.#panel?.querySelector('.VideoCallStatus');

        if (status) {
            status.textContent = text;
        }
    }

    static #setEndLabel(label) {
        const end = VideoCall.#panel?.querySelector('.VideoCallEndButton');

        if (end) {
            end.textContent = label;
        }
    }

    static #hideStage() {
        VideoCall.#stage?.remove();
        VideoCall.#stage = null;

        VideoCall.#panel?.remove();
        VideoCall.#panel = null;

        if (VideoCall.#list !== null) {
            VideoCall.#list.hidden = false;
        }

        if (VideoCall.#composer !== null) {
            VideoCall.#composer.hidden = false;
        }

        VideoCall.#startBeating();
    }

    static #stopBeating() {
        clearInterval(VideoCall.#presenceTimer);
        VideoCall.#presenceTimer = null;
    }

    static #startBeating() {
        if (VideoCall.#presenceTimer === null) {
            VideoCall.#beat();
            VideoCall.#presenceTimer = setInterval(() => VideoCall.#beat(), VideoCall.#PRESENCE_INTERVAL_MS);
        }
    }

    /**
     * Signals are ordinary requests - the WebSocket daemon carries only the
     * reply.
     *
     * Quiet, because these go every few seconds while a call is up and a run
     * of toasts about signalling would bury the call itself. Kept alive past
     * the page, so the hangup sent while somebody closes the tab still leaves
     * the browser.
     */
    static async #post(path, body) {
        return Api.post(path, body, { quiet: true, keepalive: true });
    }
}

ReadyHandler.add(VideoCall.init);

    return { VideoCall };
})();
export const VideoCall = VideoCallModule.VideoCall;

// VideoCallTestPanel.js
const VideoCallTestPanelModule = (() => {
/**
 * Mirrors VideoCallTestPanel.php: runs call setup for real, one step at a time,
 * and says which step failed and what that means for calls.
 *
 * Every step is written so a failure names a cause rather than a symptom - the
 * point of the page is that "no call button appeared" has several completely
 * different fixes behind it.
 */
class VideoCallTestPanel {
    /** How long any single step may take before it counts as hung. */
    static #STEP_TIMEOUT_MS = 8000;

    static init() {
        document.addEventListener('click', (event) => {
            const button = event.target.closest('.VideoCallTestButton');

            if (button) {
                VideoCallTestPanel.#run(button);
            }
        });
    }

    static async #run(button) {
        const words = Strings.for('VideoCallTestPanel');
        const results = document.querySelector('.VideoCallTestResults');
        const verdict = document.querySelector('.VideoCallTestVerdict');
        results.replaceChildren();
        verdict.replaceChildren();
        verdict.className = 'VideoCallTestVerdict';
        Working.start(button);

        // Which step stopped the run, if one did - the verdict is a reading of
        // that rather than of a pass count, since the steps do not all mean the
        // same thing for whether a call can happen.
        let stopped_at = null;

        for (const step of VideoCallTestPanel.#steps()) {
            const line = VideoCallTestPanel.#lineFor(step.name);
            results.appendWithSpace(line);

            let outcome;

            try {
                outcome = await step.run();
            } catch (error) {
                outcome = { ok: false, detail: (words.checkFailed || '').replace('{error}', error.message) };
            }

            VideoCallTestPanel.#settle(line, outcome);

            // A failed step invalidates everything after it - reporting those as
            // failures too would just be noise pointing away from the real cause.
            if (!outcome.ok) {
                results.appendWithSpace(VideoCallTestPanel.#note(words.stopped || ''));
                stopped_at = step.id;
                break;
            }
        }

        VideoCallTestPanel.#declare(verdict, stopped_at);

        Working.stop(button);
    }

    static #steps() {
        const words = Strings.for('VideoCallTestPanel');
        return [
            {
                id: 'secure',
                name: words.secureName || '',
                run: async () => window.isSecureContext
                    ? { ok: true, detail: words.securePass || '' }
                    : { ok: false, detail: words.secureFail || '' },
            },
            {
                id: 'webrtc',
                name: words.webrtcName || '',
                run: async () => typeof RTCPeerConnection === 'function'
                    ? { ok: true, detail: words.webrtcPass || '' }
                    : { ok: false, detail: words.webrtcFail || '' },
            },
            {
                id: 'loopback',
                name: words.loopbackName || '',
                run: () => VideoCallTestPanel.#loopback(),
            },
            {
                id: 'stun',
                name: words.stunName || '',
                run: () => VideoCallTestPanel.#stun(),
            },
            {
                id: 'signalling',
                name: words.signallingName || '',
                run: () => VideoCallTestPanel.#signalling(),
            },
        ];
    }

    /**
     * The one line the page exists to produce. Only the STUN failure is a
     * partial - it costs calls across the internet while leaving them working
     * between two people on the same network, which is a real and quite
     * different answer from "no".
     */
    static #declare(verdict, stopped_at) {
        const words = Strings.for('VideoCallTestPanel');
        const outcomes = {
            secure: { state: 'Failed', text: words.secureVerdict },
            webrtc: { state: 'Failed', text: words.webrtcVerdict },
            loopback: { state: 'Failed', text: words.loopbackVerdict },
            stun: { state: 'Partial', text: words.stunVerdict },
            signalling: { state: 'Failed', text: words.signallingVerdict },
        };
        const outcome = outcomes[stopped_at] ?? { state: 'Passed', text: words.passVerdict };

        verdict.className = 'VideoCallTestVerdict ' + outcome.state;
        verdict.textContent = outcome.text;
    }

    /**
     * Two peer connections in this page, connected to each other with a data
     * channel and no network involved. It proves the browser's own stack works
     * before anything blames the network for a failure further along.
     */
    static async #loopback() {
        const words = Strings.for('VideoCallTestPanel');
        const caller = new RTCPeerConnection();
        const callee = new RTCPeerConnection();

        try {
            caller.onicecandidate = (event) => event.candidate && callee.addIceCandidate(event.candidate);
            callee.onicecandidate = (event) => event.candidate && caller.addIceCandidate(event.candidate);

            // The channel is opened outside the executor on purpose: a stack
            // that is present but unusable throws here, and inside the executor
            // that throw becomes a rejected promise nothing is waiting on yet -
            // an unhandled rejection instead of the failure this step exists to
            // report.
            const channel = caller.createDataChannel('check');

            const opened = new Promise((resolve) => {
                channel.onopen = () => resolve(true);
            });

            const offer = await caller.createOffer();
            await caller.setLocalDescription(offer);
            await callee.setRemoteDescription(offer);

            const answer = await callee.createAnswer();
            await callee.setLocalDescription(answer);
            await caller.setRemoteDescription(answer);

            const connected = await VideoCallTestPanel.#within(opened);

            return connected
                ? { ok: true, detail: words.loopbackPass || '' }
                : { ok: false, detail: words.loopbackFail || '' };
        } finally {
            caller.close();
            callee.close();
        }
    }

    /**
     * The ICE configuration a real call would use, carried on the panel by
     * VideoCallTestPanel.php.
     */
    static #iceServers() {
        const panel = document.querySelector('.VideoCallTestPanel');

        return panel === null ? [] : (JSON.parse(panel.dataset.iceServers ?? '[]') ?? []);
    }

    /**
     * Whether STUN answers from here. A server-reflexive candidate is the
     * browser being told what its own address looks like from outside; without
     * one, two people behind different routers have no way to find each other.
     */
    static async #stun() {
        const words = Strings.for('VideoCallTestPanel');
        const ice_servers = VideoCallTestPanel.#iceServers();

        if (ice_servers.length === 0) {
            return { ok: false, detail: words.noStun || '' };
        }

        const connection = new RTCPeerConnection({ iceServers: ice_servers });

        try {
            const reflexive = new Promise((resolve) => {
                connection.onicecandidate = (event) => {
                    if (event.candidate === null) {
                        resolve(false);
                    } else if (event.candidate.type === 'srflx') {
                        resolve(true);
                    }
                };
            });

            connection.createDataChannel('check');
            await connection.setLocalDescription(await connection.createOffer());

            const found = await VideoCallTestPanel.#within(reflexive);

            return found
                ? { ok: true, detail: words.stunPass || '' }
                : { ok: false, detail: words.stunFail || '' };
        } finally {
            connection.close();
        }
    }

    /**
     * That the signalling path is reachable, authenticated and passes CSRF. It
     * deliberately addresses a call to the admin themselves, which the endpoint
     * refuses - reaching that refusal is what proves everything in front of it
     * worked, and nothing is left behind.
     */
    static async #signalling() {
        const words = Strings.for('VideoCallTestPanel');
        // request() rather than post(): the status IS the result here, and a
        // refusal is what this step is hoping for rather than something to
        // announce.
        const { status } = await Api.request('/api/call-signal', {
            otherUserId: ClientConfig.get('currentUserId'),
            type: 'hangup',
            signal: null,
        });

        if (status === 422) {
            return { ok: true, detail: words.signallingPass || '' };
        }

        if (status === 401 || status === 403) {
            return { ok: false, detail: (words.signallingAuthFail || '').replace('{status}', String(status)) };
        }

        return { ok: false, detail: (words.signallingUnexpected || '').replace('{status}', String(status)) };
    }

    /** Resolves false if the promise has not settled before the step timeout. */
    static #within(promise) {
        return Promise.race([
            promise,
            new Promise((resolve) => setTimeout(() => resolve(false), VideoCallTestPanel.#STEP_TIMEOUT_MS)),
        ]);
    }

    static #lineFor(name) {
        const line = document.createElement('li');
        line.className = 'VideoCallTestStep';

        const label = document.createElement('strong');
        label.textContent = name;
        line.appendWithSpace(label);

        const detail = document.createElement('div');
        detail.className = 'VideoCallTestDetail';
        detail.textContent = Strings.for('MiscellaneousClient').checking || '';
        line.appendWithSpace(detail);

        return line;
    }

    static #settle(line, outcome) {
        line.classList.add(outcome.ok ? 'Passed' : 'Failed');
        line.querySelector('.VideoCallTestDetail').textContent = outcome.detail;
    }

    static #note(text) {
        const note = document.createElement('li');
        note.className = 'VideoCallTestNote';
        note.textContent = text;

        return note;
    }
}

ReadyHandler.add(VideoCallTestPanel.init);

    return { VideoCallTestPanel };
})();
export const VideoCallTestPanel = VideoCallTestPanelModule.VideoCallTestPanel;

// WebSocketManager.js
const WebSocketManagerModule = (() => {
class WebSocketManager {
    constructor() {
        this.socket = null;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 3;
        this.reconnectDelay = 10000;
        this.token = null;
        this.reconnecting = false;
        // The page's own title, kept so the unread marker can be put in front of
        // it and taken back off. Remembered here rather than parked on the
        // <title> element, since it's this manager's bookkeeping.
        this.pageTitle = document.title;
        // NO this.init() here – init is called explicitly from main.js
    }

    init() {
        if (ClientConfig.get('currentUserId') === null) {
            return;
        }

        this.connect();

        const navLink = document.querySelector('.NotificationsNavLink');
        if (navLink && ClientConfig.get('currentUserId') !== null) {
            navLink.addEventListener('mouseenter', async () => {
                const dot = navLink.querySelector('.NotificationDot');
                if (!dot?.classList.contains('Active')) return;

                dot.classList.remove('Active');
                document.title = this.pageTitle;

                // Quiet: nobody asked for this, it happened because they moved
                // the mouse. If it fails the dot simply comes back, which says
                // everything a toast would and interrupts nothing.
                if (await Api.post('/api/mark-notifications-seen', undefined, { quiet: true }) === null) {
                    dot.classList.add('Active');
                }
            });
        }
    }

    async connect() {
        // Quiet: this reconnects on its own schedule and can fail a dozen
        // times while a laptop is asleep. A dozen toasts about a socket
        // nobody asked about is worse than the socket being down.
        const token = await Api.post('/api/ws-token', undefined, { quiet: true });

        if (token === null) {
            console.error('WebSocket token fetch failed');
            this.scheduleReconnect();

            return;
        }

        this.token = token.token;

        // Nothing in the address. A handshake is a GET and this API will not
        // set headers, so a token here could only ride in the URL - which is
        // the one part of a request that gets written down along the way. It
        // goes as the first message instead, inside the same encrypted channel
        // as everything after it, and the server tells nobody anything until
        // it has read one.
        const scheme = window.location.protocol === 'https:' ? 'wss' : 'ws';
        this.socket = new WebSocket(`${scheme}://${window.location.hostname}:${ClientConfig.wsPort()}/`);

        this.socket.addEventListener('open', () => {
            this.socket.send(this.token);
            this.reconnectAttempts = 0;

            const statusLine = document.querySelector('.WebSocketClientStatus');
            if (statusLine) {
                this.showStatus(statusLine);
            }
        });

        this.socket.addEventListener('message', (event) => {
            let data;
            try {
                data = JSON.parse(event.data);
            } catch (e) {
                return;
            }

            if (data.event === 'notification') {
                this.handleNotification(data.notification);
            } else if (data.event === 'message') {
                document.dispatchEvent(new CustomEvent('ws:message', { detail: data.message }));

                // Somewhere other than the conversations list, where opening
                // the page is what clears the mark - marking it read from
                // under the reader while they are elsewhere would lose it.
                if (!window.location.pathname.startsWith('/messages')) {
                    document.querySelectorAll('.MessageDot, .NavAlertDot').forEach(dot => dot.classList.add('Active'));
                }
            } else if (data.event === 'call') {
                document.dispatchEvent(new CustomEvent('ws:call', { detail: data.call }));
            }
        });

        this.socket.addEventListener('close', () => {
            // Only on a real change of state. Saying it on page load instead
            // would replace the line the server rendered - in the reader's own
            // language - with this one, before anything had happened.
            const statusLine = document.querySelector('.WebSocketClientStatus');

            if (statusLine) {
                this.showStatus(statusLine);
            }

            this.scheduleReconnect();
        });
        this.socket.addEventListener('error', () => this.socket?.close());
    }

    scheduleReconnect() {
        if (this.reconnecting) return;
        this.reconnecting = true;

        if (this.reconnectAttempts >= this.maxReconnectAttempts) {
            Toast.show(Strings.for('MiscellaneousClient').reloadError || '');
            return;
        }

        this.reconnectAttempts += 1;
        setTimeout(() => {
            this.reconnecting = false;
            this.connect();
        }, this.reconnectDelay);
    }

    handleNotification(notificationData) {
        const notification = Notification.fromData(notificationData);

        const toastTarget = notification.targetURL();
        const toastContent = document.createElement(toastTarget === null ? 'span' : 'a');
        if (toastTarget !== null) toastContent.href = toastTarget;
        toastContent.textContent = notification.text();
        Toast.show(toastContent);

            const dropdownList = list_in(document.querySelector('.NotificationDropdown'), 'NotificationList RecentNotificationList');
        if (dropdownList) {
            const existing = dropdownList.querySelectorAll('.Notification');
            if (existing.length >= 5) {
                existing[existing.length - 1].closest('li').remove();
            }

            const element = notification.toElement();
            RelativeTime.refresh(element);
            dropdownList.insertBeforeWithSpace(list_item(element), dropdownList.firstChild);
        }

            const pageList = list_in(document.querySelector('.NotificationsPage main'), 'NotificationList');
        if (pageList) {
            const element = notification.toElement();
            RelativeTime.refresh(element);
            pageList.insertBeforeWithSpace(list_item(element), pageList.firstChild);
        }

        document.querySelectorAll('.NotificationDot, .NavAlertDot').forEach(dot => dot.classList.add('Active'));
        document.title = '🔴 ' + this.pageTitle;
    }

    // Keyed on WebSocketStatus, which is the element the server rendered and
    // the words this replaces: one entry says the line in both renderers.
    showStatus(statusLine) {
        const words = Strings.for('WebSocketStatus', {
            clientConnecting: 'Browser connection: Connecting…',
            clientConnected: 'Browser connection: Connected',
            clientDisconnecting: 'Browser connection: Disconnecting…',
            clientNotConnected: 'Browser connection: Not connected',
        });

        const states = {
            [WebSocket.CONNECTING]: words.clientConnecting,
            [WebSocket.OPEN]:       words.clientConnected,
            [WebSocket.CLOSING]:    words.clientDisconnecting,
            [WebSocket.CLOSED]:     words.clientNotConnected,
        };

        statusLine.textContent = (this.socket ? states[this.socket.readyState] : null) || words.clientNotConnected;

        if (this.socket?.readyState === WebSocket.OPEN) {
            statusLine.classList.remove('Error');
        } else {
            statusLine.classList.add('Error');
        }
    }
}

    return { WebSocketManager };
})();
export const WebSocketManager = WebSocketManagerModule.WebSocketManager;

// WelcomeBanner.js
const WelcomeBannerModule = (() => {
// WelcomeBanner.js
/**
 * Closing the welcome on the home feed.
 *
 * Two different closings behind one button. Ticked, it is gone for good and
 * the server is told so; unticked, it goes away here and comes back next
 * time, which needs telling nobody - somebody who closed it before finishing
 * reading has not decided anything.
 */
class WelcomeBanner {
    static init() {
        const banner = document.querySelector('.WelcomeBanner');
        if (!banner) return;

        const dismiss = banner.querySelector('.WelcomeBannerDismissButton');
        const forGood = banner.querySelector('[name="welcomeDismissed"]');
        if (!dismiss) return;

        dismiss.addEventListener('click', async () => {
            Working.start(dismiss);

            if (forGood?.checked) {
                const result = await Api.post('/api/dismiss-welcome', { forGood: true });

                // Left standing when the server would not record it, rather
                // than vanishing on a promise it did not make.
                if (!result) {
                    Working.stop(dismiss);

                    return;
                }
            }

            banner.remove();
        });
    }
}

ReadyHandler.add(WelcomeBanner.init);

    return { WelcomeBanner };
})();
export const WelcomeBanner = WelcomeBannerModule.WelcomeBanner;
