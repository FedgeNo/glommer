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
export function expandInDOM(root) {
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
        const expanded = expand(node.data);

        if (expanded !== node.data) {
            node.data = expanded;
        }
    });
}
