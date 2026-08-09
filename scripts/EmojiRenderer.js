const EMOJI_SEQUENCE = /\p{Emoji_Presentation}\uFE0F?\p{Emoji_Modifier}?(\u200D\p{Emoji}\uFE0F?\p{Emoji_Modifier}?)*|\p{Emoji}\uFE0F\p{Emoji_Modifier}?(\u200D\p{Emoji}\uFE0F?\p{Emoji_Modifier}?)*/gu;
import { ReadyHandler } from '/scripts/ReadyHandler.js';

/** What counts as one character to the person who typed it. */
const GRAPHEMES = new Intl.Segmenter(undefined, { granularity: 'grapheme' });

export class EmojiRenderer {
    /**
     * Where an emoji is somebody's writing rather than part of the page.
     *
     * Enlarging one is only ever right inside what somebody wrote. Everywhere
     * else - an action bar's buttons, a display name, a topic, a nav label -
     * the emoji IS the furniture and is already sized by its own rules.
     */
    static CONTENT = '.PostContent, .MessageLine';

    static init() {
        document.querySelectorAll(EmojiRenderer.CONTENT).forEach(content => EmojiRenderer.render(content));
        EmojiRenderer.#markExistingEmojiOnly();
    }

    static render(root) {
        const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
            acceptNode: node => {
                if (node.parentElement.closest('.emoji-text, pre, code, .katex, .PostFormula')) {
                    return NodeFilter.FILTER_REJECT;
                }
                // The regex is global, so a previous test() leaves lastIndex
                // mid-string - unreset, the next node's test starts there and
                // can miss an emoji earlier in its text.
                EMOJI_SEQUENCE.lastIndex = 0;
                return EMOJI_SEQUENCE.test(node.data) ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_SKIP;
            }
        });

        const nodes = [];
        while (walker.nextNode()) {
            nodes.push(walker.currentNode);
        }

        nodes.forEach(textNode => {
            const parent = textNode.parentNode;
            const fragment = document.createDocumentFragment();
            let plain = '';

            // Cut into grapheme clusters and decide per cluster, rather than
            // wrapping whatever the pattern happened to match. A flag is a
            // PAIR of regional indicators with nothing joining them, so a
            // pattern matching one emoji at a time takes 🇺🇸 as two and puts
            // each in its own span - which is why flags came out as two big
            // letters. Keycaps and joined families split the same way.
            // The cluster is the unit the writer typed and the unit a font
            // draws, so it is the unit to wrap.
            for (const { segment } of GRAPHEMES.segment(textNode.data)) {
                EMOJI_SEQUENCE.lastIndex = 0;

                if (!EMOJI_SEQUENCE.test(segment)) {
                    plain += segment;

                    continue;
                }

                if (plain !== '') {
                    fragment.appendChild(document.createTextNode(plain));
                    plain = '';
                }

                const span = document.createElement('span');
                span.className = 'emoji-text';
                span.textContent = segment;
                fragment.appendChild(span);
            }

            if (plain !== '') {
                fragment.appendChild(document.createTextNode(plain));
            }

            parent.replaceChild(fragment, textNode);
        });
    }

    /**
     * Whether this is emoji and nothing else - what decides that a post or a
     * message is shown big and centred.
     *
     * Cluster by cluster, for the same reason render() is: stripping matches
     * out of the text and asking whether anything is left leaves behind the
     * parts of a sequence the pattern does not reach - a keycap's enclosing
     * mark, say - and one leftover character answers no.
     */
    static isEmojiOnly(element) {
        let sawEmoji = false;

        for (const { segment } of GRAPHEMES.segment(element.textContent)) {
            // trim() takes the no-break space too, so nothing separating the
            // emoji counts against them.
            if (segment.trim() === '') {
                continue;
            }

            EMOJI_SEQUENCE.lastIndex = 0;

            if (!EMOJI_SEQUENCE.test(segment)) {
                return false;
            }

            sawEmoji = true;
        }

        return sawEmoji;
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    static #markExistingEmojiOnly() {
        // Posts
        document.querySelectorAll('.PostBody').forEach(body => {
            if (EmojiRenderer.isEmojiOnly(body)) {
                const card = body.closest('.Post');
                if (card) card.classList.add('emoji-only');
            }
        });

        // Messages
        document.querySelectorAll('.MessageLine p').forEach(body => {
            if (EmojiRenderer.isEmojiOnly(body)) {
                const card = body.closest('.Message');
                if (card) card.classList.add('emoji-only');
            }
        });
    }
}

ReadyHandler.add(EmojiRenderer.init);

