/**
 * Renders a Quill Delta (an array of insert ops) to real DOM nodes - no
 * innerHTML, no HTML strings. Mirrors the server-side DeltaRenderer so a post
 * looks identical whether it arrived in the initial page or over AJAX.
 *
 * Quill's model: inline runs are ops whose insert is a string (with inline
 * attributes like bold/link); a line's block type lives on the "\n" that ends
 * it (header/list/blockquote/code-block). So we buffer inline nodes per line
 * and flush a block element when a newline names the block. Math is either a
 * formula embed op ({insert:{formula:'...'}}, rendered directly via KaTeX) or
 * typed/pasted delimiters left in the text (rendered afterward by render_math).
 *
 * @param {Array} ops  the Delta's ops array
 * @returns {HTMLElement} a .PostBody div containing the rendered content
 */
function render_delta(ops) {
    const root = document.createElement('div');
    root.className = 'PostBody';

    if (!Array.isArray(ops)) {
        return root;
    }

    let inline = [];      // inline nodes accumulated for the current line
    let list_el = null;   // the <ol>/<ul> currently being filled, or null
    let list_kind = null; // 'ordered' | 'bullet'

    const ALLOWED_LINK_SCHEMES = ['http:', 'https:', 'mailto:'];

    const inline_node = (text, attributes) => {
        const attrs = attributes || {};
        let node = document.createTextNode(text);

        // Wrap the text node outward through each active inline format.
        if (attrs.code) {
            const code = document.createElement('code');
            code.appendChild(node);
            node = code;
        }
        if (attrs.bold) {
            const strong = document.createElement('strong');
            strong.appendChild(node);
            node = strong;
        }
        if (attrs.italic) {
            const em = document.createElement('em');
            em.appendChild(node);
            node = em;
        }
        if (attrs.underline) {
            const u = document.createElement('u');
            u.appendChild(node);
            node = u;
        }
        if (attrs.strike) {
            const s = document.createElement('s');
            s.appendChild(node);
            node = s;
        }
        if (typeof attrs.link === 'string' && is_safe_link(attrs.link, ALLOWED_LINK_SCHEMES)) {
            const anchor = document.createElement('a');
            anchor.setAttribute('href', attrs.link);
            anchor.setAttribute('target', '_blank');
            anchor.setAttribute('rel', 'noopener');
            anchor.appendChild(node);
            node = anchor;
        }

        return node;
    };

    const formula_node = (source) => {
        // Carries the LaTeX source; render_formulas() renders it via KaTeX
        // (the same client pass that renders server-emitted formula spans), so
        // math from a formula embed and math from a server render take one
        // path. The textContent is the fallback if KaTeX never runs.
        const span = document.createElement('span');
        span.className = 'PostFormula';
        span.setAttribute('data-formula', source);
        span.textContent = source;
        return span;
    };

    const flush_line = (blockAttributes) => {
        const attrs = blockAttributes || {};

        // List items group consecutive same-kind lines under one <ol>/<ul>.
        if (attrs.list === 'ordered' || attrs.list === 'bullet') {
            if (list_el === null || list_kind !== attrs.list) {
                list_el = document.createElement(attrs.list === 'ordered' ? 'ol' : 'ul');
                list_kind = attrs.list;
                root.appendChild(list_el);
            }

            const li = document.createElement('li');
            inline.forEach((n) => li.appendChild(n));
            list_el.appendChild(li);
            inline = [];
            return;
        }

        // Any non-list line closes an open list.
        list_el = null;
        list_kind = null;

        let block;
        if (attrs.header === 1 || attrs.header === 2 || attrs.header === 3) {
            block = document.createElement('h' + attrs.header);
        } else if (attrs.blockquote) {
            block = document.createElement('blockquote');
        } else if (attrs['code-block']) {
            block = document.createElement('pre');
        } else {
            block = document.createElement('p');
        }

        inline.forEach((n) => block.appendChild(n));

        // An empty line (Quill renders it as <p><br></p>) still takes space.
        if (inline.length === 0 && block.tagName === 'P') {
            block.appendChild(document.createElement('br'));
        }

        root.appendChild(block);
        inline = [];
    };

    ops.forEach((op) => {
        if (typeof op.insert === 'string') {
            // Each "\n" in the string ends a line; its block type comes from
            // this op's attributes (Quill puts block attrs on the newline op).
            const segments = op.insert.split('\n');

            segments.forEach((text, index) => {
                if (text !== '') {
                    inline.push(inline_node(text, op.attributes));
                }

                if (index < segments.length - 1) {
                    flush_line(op.attributes);
                }
            });
        } else if (op.insert && typeof op.insert === 'object' && typeof op.insert.formula === 'string') {
            inline.push(formula_node(op.insert.formula));
        }
        // Other embed types (none authored by this app) are ignored.
    });

    // A Quill delta always ends with a trailing "\n", so `inline` is normally
    // empty here; flush anything left just in case of a malformed delta.
    if (inline.length > 0) {
        flush_line({});
    }

    return root;
}

/**
 * The "See More..." link a truncated feed preview appends, linking to the full
 * post. Mirrors the server-side SeeMore class (same SeeMore class name, text,
 * and href) so a truncated post looks identical whether it arrived in the page
 * or over AJAX.
 *
 * @param {string} url  the full post's URL
 * @returns {HTMLElement} an <a class="SeeMore">
 */
function see_more_element(url) {
    const anchor = document.createElement('a');
    anchor.className = 'SeeMore';
    anchor.href = url;
    anchor.textContent = 'See More...';
    return anchor;
}

/**
 * Whether a link URL is safe to render (a known scheme, or a scheme-relative /
 * relative URL). Blocks javascript:, data:, etc. Server-side validation is the
 * real gate; this is client-side defense in depth.
 */
function is_safe_link(url, allowed_schemes) {
    // Browsers strip ASCII whitespace/control chars while parsing a URL, so
    // "java\tscript:" would run; strip them (interior ones too) before the
    // scheme test. Mirrors DeltaRenderer::isSafeLink().
    const stripped = url.replace(/[\u0000-\u0020]+/g, '');
    const match = /^([a-z][a-z0-9+.-]*):/i.exec(stripped);

    if (match === null) {
        return true; // relative or scheme-relative URL - no scheme to abuse
    }

    return allowed_schemes.includes(match[1].toLowerCase() + ':');
}
