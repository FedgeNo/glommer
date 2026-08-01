import { EMOJI_SHORTCODES } from '/emoji-shortcodes.js';

/**
 * Turns :shortcode: into the emoji it names - the client half of
 * EmojiShortcode.php, working off the same table, served by it.
 *
 * The table is imported rather than duplicated. It is hard-coded once in
 * EmojiShortcodeMap.php and handed here as data, so there is no second copy to
 * drift and nothing writes executable source from anything fetched.
 *
 * Only ever at the last step of output. What someone typed is never rewritten,
 * so the composer, the stored post and an edit all still say exactly that; only
 * what is rendered carries the emoji.
 *
 * A name this table does not hold is left alone, which is what keeps a clock
 * time, a ratio and a custom emoji intact - the last of those so a per-post
 * Emoji tag can resolve it later.
 */

const SHORTCODE = /:([a-z0-9_+-]+):/gi;

/** Where a colon means something other than an emoji. */
const CODE_CONTEXT = 'pre, code, .katex, .PostFormula';

export function expand(text) {
    // Most text has no colons at all, and scanning it is the common case.
    if (!text.includes(':')) {
        return text;
    }

    return text.replace(SHORTCODE, (whole, name) => EMOJI_SHORTCODES[name.toLowerCase()] ?? whole);
}

/**
 * Expands every shortcode under a node, except inside code.
 *
 * Walks the finished tree rather than substituting while it is built, for the
 * same reason the server does: a code block is known only once it exists. The
 * skip list matches EmojiRenderer's, so the two passes agree about what is
 * left alone.
 */
export function expandInDOM(root, custom = {}) {
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
        acceptNode: node => {
            if (node.parentElement?.closest(CODE_CONTEXT)) {
                return NodeFilter.FILTER_REJECT;
            }

            return node.data.includes(':') ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_SKIP;
        }
    });

    // Collected before changing anything: editing a node's data while the
    // walker is still traversing is asking for a surprise.
    const nodes = [];

    while (walker.nextNode()) {
        nodes.push(walker.currentNode);
    }

    nodes.forEach(node => {
        const replacement = fragmentFor(node.data, custom);

        if (replacement !== null) {
            node.parentNode?.replaceChild(replacement, node);
        }
    });
}

/**
 * One text node's worth of expansion, or null when nothing in it changes.
 *
 * A custom emoji becomes an image and so needs real nodes, which is why this
 * builds a fragment rather than a string. A Unicode one is still just text.
 *
 * The custom map wins where both know a name: a tag is the sending server
 * stating what a shortcode means in THIS post, which is a more specific claim
 * than a table everyone shares.
 */
function fragmentFor(text, custom) {
    if (!text.includes(':')) {
        return null;
    }

    const fragment = document.createDocumentFragment();
    let cursor = 0;
    let changed = false;

    SHORTCODE.lastIndex = 0;

    let match;

    while ((match = SHORTCODE.exec(text)) !== null) {
        const name = match[1].toLowerCase();
        const image = custom[name];
        const character = EMOJI_SHORTCODES[name];

        if (image === undefined && character === undefined) {
            continue;
        }

        if (match.index > cursor) {
            fragment.appendChild(document.createTextNode(text.slice(cursor, match.index)));
        }

        if (image !== undefined) {
            const element = document.createElement('img');
            element.className = 'CustomEmoji';
            element.src = image;
            // The shortcode is the alt text: it is what the author wrote, and
            // the only description of the picture that exists.
            element.alt = `:${name}:`;
            element.title = `:${name}:`;
            element.loading = 'lazy';
            fragment.appendChild(element);
        } else {
            fragment.appendChild(document.createTextNode(character));
        }

        cursor = match.index + match[0].length;
        changed = true;
    }

    if (!changed) {
        return null;
    }

    if (cursor < text.length) {
        fragment.appendChild(document.createTextNode(text.slice(cursor)));
    }

    return fragment;
}
