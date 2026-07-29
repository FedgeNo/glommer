const EMOJI_SEQUENCE = /\p{Emoji_Presentation}\uFE0F?\p{Emoji_Modifier}?(\u200D\p{Emoji}\uFE0F?\p{Emoji_Modifier}?)*|\p{Emoji}\uFE0F\p{Emoji_Modifier}?(\u200D\p{Emoji}\uFE0F?\p{Emoji_Modifier}?)*/gu;
import { ReadyHandler } from '/scripts/ReadyHandler.js';

export class EmojiRenderer {
    static init() {
        // 1. Wrap emojis across the entire page
        EmojiRenderer.render(document.body);
        // 2. Mark existing emoji‑only posts / messages
        EmojiRenderer.#markExistingEmojiOnly();
    }

    static render(root) {
        const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
            acceptNode: node => {
                if (node.parentElement.closest('.emoji-text, pre, code, .katex, .PostFormula')) {
                    return NodeFilter.FILTER_REJECT;
                }
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
            let lastIndex = 0;

            EMOJI_SEQUENCE.lastIndex = 0;
            let match;
            while ((match = EMOJI_SEQUENCE.exec(textNode.data)) !== null) {
                const matched = match[0];
                const offset = match.index;

                if (offset > lastIndex) {
                    fragment.appendChild(document.createTextNode(textNode.data.slice(lastIndex, offset)));
                }

                const span = document.createElement('span');
                span.className = 'emoji-text';
                span.textContent = matched;
                fragment.appendChild(span);

                lastIndex = offset + matched.length;
            }

            if (lastIndex < textNode.data.length) {
                fragment.appendChild(document.createTextNode(textNode.data.slice(lastIndex)));
            }

            parent.replaceChild(fragment, textNode);
        });
    }

    static isEmojiOnly(element) {
        const text = element.textContent;
        const stripped = text.replace(EMOJI_SEQUENCE, '').replace(/[\s\u00A0]/g, '');
        if (stripped.length !== 0) return false;
        EMOJI_SEQUENCE.lastIndex = 0;
        return EMOJI_SEQUENCE.test(text);
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

