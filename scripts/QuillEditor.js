import { Strings } from '/scripts/Strings.js';

export class QuillEditor {
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
