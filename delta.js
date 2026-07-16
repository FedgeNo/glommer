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
 * Runs the "honest links" pass (see the linkify_* helpers), kept identical to
 * DeltaRenderer: pass 1 strips the href off any link whose text reads as a URL
 * (anti-phishing), pass 2 linkifies bare URLs and #hashtags. External links open
 * in a new tab; internal/hashtag links open in place.
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

    // Pass 1: neutralise deceptive anchors before rendering.
    ops = strip_deceptive_links(ops);

    let inline = [];      // inline nodes accumulated for the current line
    let list_el = null;   // the <ol>/<ul> currently being filled, or null
    let list_kind = null; // 'ordered' | 'bullet'

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

    const flush_line = (block_attributes) => {
        const attrs = block_attributes || {};

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
                    inline.push(...inline_nodes(text, op.attributes));
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

const ALLOWED_LINK_SCHEMES = ['http:', 'https:', 'mailto:'];

/**
 * Pass 1: group consecutive string ops sharing a link value and, if the group's
 * combined text reads as a URL, strip the link from all of them (mirrors
 * DeltaRenderer::stripDeceptiveLinks). Grouping - not per-op - is what stops a
 * URL split across formatting ops from keeping a deceptive href.
 */
function strip_deceptive_links(ops) {
    const result = [];
    let group = [];
    let group_text = '';
    let group_link = null;

    const resolve = () => {
        if (group.length > 0 && linkify_text_looks_url(group_text)) {
            group.forEach((i) => {
                const attrs = { ...result[i].attributes };
                delete attrs.link;
                result[i] = { ...result[i], attributes: attrs };
            });
        }
        group = [];
        group_text = '';
        group_link = null;
    };

    ops.forEach((op) => {
        const link = typeof op.insert === 'string' && op.attributes ? op.attributes.link : undefined;

        if (typeof link === 'string') {
            if (group_link !== null && link !== group_link) {
                resolve();
            }
            result.push(op);
            group.push(result.length - 1);
            group_text += op.insert;
            group_link = link;
        } else {
            resolve();
            result.push(op);
        }
    });

    resolve();

    return result;
}

/**
 * Pass 2: the inline node(s) for one text run (mirrors DeltaRenderer::inlineNodes).
 * A link that survived pass 1 is a URL-free label -> one honest anchor; inline
 * code is never linkified; otherwise URLs become self-links, #hashtags tag
 * links, the rest plain - each wrapped in the run's formatting, anchor outermost.
 */
function inline_nodes(text, attributes) {
    const attrs = attributes || {};

    if (typeof attrs.link === 'string') {
        return [linked_node(attrs.link, formatted_text_node(text, attrs))];
    }

    if (attrs.code) {
        return [formatted_text_node(text, attrs)];
    }

    return linkify_tokenize(text).map((segment) => {
        const inner = formatted_text_node(segment.text, attrs);

        if (segment.type === 'url') {
            return linked_node(segment.text, inner);
        }
        if (segment.type === 'hashtag') {
            return hashtag_node(segment.tag, inner);
        }
        if (segment.type === 'mention') {
            return mention_node(segment.username, inner);
        }
        return inner;
    });
}

/** A text node wrapped in the run's inline formatting (no link). */
function formatted_text_node(text, attrs) {
    let node = document.createTextNode(text);

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

    return node;
}

/** An anchor to href (external -> new tab), or the bare node if unsafe. */
function linked_node(href, inner) {
    if (!is_safe_link(href, ALLOWED_LINK_SCHEMES)) {
        return inner;
    }

    const anchor = document.createElement('a');
    anchor.setAttribute('href', href);

    if (opens_in_new_tab(href)) {
        anchor.setAttribute('target', '_blank');
        anchor.setAttribute('rel', 'noopener');
    }

    anchor.appendChild(inner);
    return anchor;
}

/** An internal (same-window) anchor to a hashtag's tag page. */
function hashtag_node(tag, inner) {
    const anchor = document.createElement('a');
    anchor.setAttribute('href', window.siteURL + '/tags/' + tag);
    anchor.appendChild(inner);
    return anchor;
}

/** An internal (same-window) anchor to a mentioned user's profile. */
function mention_node(username, inner) {
    const anchor = document.createElement('a');
    anchor.setAttribute('href', window.siteURL + '/users/' + username + '/');
    anchor.appendChild(inner);
    return anchor;
}

function opens_in_new_tab(href) {
    const host = linkify_link_host(href);

    if (host === null) {
        return false;
    }

    return host !== linkify_link_host(window.siteURL);
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

/*
 * Linkify mirror of src/classes/Linkify.php - the constants and logic are pinned
 * byte-for-byte to the PHP so both renderers produce identical DOM. ASCII-only
 * classes, no \s/\w/\b, no unicode flag, one URL-first left-to-right scan, fresh
 * RegExp per call (so the g-flag's lastIndex never leaks between calls).
 */
const LINKIFY_MAX_TAG_LENGTH = 50;
const LINKIFY_MAX_MENTION_LENGTH = 50;
const LINKIFY_URL_TRAILING_TRIM = ".,!?;:)";
const LINKIFY_SCAN = "https?://[A-Za-z0-9._~:/?#\\[\\]@!$&'()*+,;=%-]+|(?<![A-Za-z0-9_#])#[A-Za-z0-9_]+|(?<![A-Za-z0-9_@])@[A-Za-z0-9_]+";
const LINKIFY_LOOKS_URL = "https?://|www\\.[A-Za-z0-9-]|[A-Za-z0-9-]+\\.[A-Za-z][A-Za-z]+/";
const LINKIFY_AUTHORITY = "^(?:[A-Za-z][A-Za-z0-9+.-]*:)?//([^/?#]*)";

function linkify_text_looks_url(text) {
    return new RegExp(LINKIFY_LOOKS_URL).test(text);
}

function linkify_link_host(url) {
    const stripped = url.replace(/[\u0000-\u0020]+/g, '');
    const match = new RegExp(LINKIFY_AUTHORITY).exec(stripped);

    if (match === null) {
        return null;
    }

    let authority = match[1];
    const at = authority.lastIndexOf('@');

    if (at !== -1) {
        authority = authority.slice(at + 1);
    }

    const colon = authority.indexOf(':');

    if (colon !== -1) {
        authority = authority.slice(0, colon);
    }

    return authority.toLowerCase();
}

function linkify_tokenize(text) {
    const segments = [];
    let cursor = 0;
    const re = new RegExp(LINKIFY_SCAN, 'g');
    let match;

    while ((match = re.exec(text)) !== null) {
        const matched = match[0];
        const offset = match.index;
        const classified = linkify_classify(matched);

        if (classified === null) {
            continue;
        }

        if (offset > cursor) {
            segments.push({ type: 'text', text: text.slice(cursor, offset) });
        }

        segments.push(classified.segment);
        cursor = offset + matched.length;

        if (classified.trailing !== '') {
            segments.push({ type: 'text', text: classified.trailing });
        }
    }

    if (cursor < text.length) {
        segments.push({ type: 'text', text: text.slice(cursor) });
    }

    return linkify_merge_text(segments);
}

function linkify_classify(matched) {
    if (matched[0] === '#') {
        const tag = matched.slice(1);

        if (tag === '' || tag.length > LINKIFY_MAX_TAG_LENGTH || !/[A-Za-z]/.test(tag)) {
            return null;
        }

        return { segment: { type: 'hashtag', text: matched, tag: tag.toLowerCase() }, trailing: '' };
    }

    if (matched[0] === '@') {
        const username = matched.slice(1);

        if (username === '' || username.length > LINKIFY_MAX_MENTION_LENGTH) {
            return null;
        }

        // Lowercased for both display and the link - unlike a hashtag, a
        // username is always stored lowercase, so there's no legitimate
        // original casing to keep. Mirrors Linkify::classify() exactly.
        const lowercased = username.toLowerCase();

        return { segment: { type: 'mention', text: '@' + lowercased, username: lowercased }, trailing: '' };
    }

    let end = matched.length;

    while (end > 0 && LINKIFY_URL_TRAILING_TRIM.includes(matched[end - 1])) {
        end--;
    }

    const url = matched.slice(0, end);
    const trailing = matched.slice(end);

    if (!/^https?:\/\/./.test(url)) {
        return null;
    }

    return { segment: { type: 'url', text: url }, trailing };
}

function linkify_merge_text(segments) {
    const merged = [];

    segments.forEach((segment) => {
        const last = merged.length - 1;

        if (segment.type === 'text' && last >= 0 && merged[last].type === 'text') {
            merged[last].text += segment.text;
            return;
        }

        merged.push(segment);
    });

    return merged;
}
