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

        const titles = {
            'ql-bold': 'Bold',
            'ql-italic': 'Italic',
            'ql-underline': 'Underline',
            'ql-strike': 'Strikethrough',
            'ql-blockquote': 'Blockquote',
            'ql-code-block': 'Code block',
            'ql-code': 'Inline code',
            'ql-link': 'Link',
            'ql-formula': 'Formula',
            'ql-clean': 'Clear formatting',
        };

        Object.entries(titles).forEach(([cls, title]) => {
            const btn = toolbar.querySelector('button.' + cls);
            if (btn) btn.title = title;
        });

        toolbar.querySelectorAll('button.ql-header[value]').forEach(btn => {
            btn.title = 'Heading ' + btn.getAttribute('value');
        });

        const listTitles = { ordered: 'Numbered list', bullet: 'Bullet list' };
        toolbar.querySelectorAll('button.ql-list[value]').forEach(btn => {
            btn.title = listTitles[btn.getAttribute('value')] || 'List';
        });
    }
}
