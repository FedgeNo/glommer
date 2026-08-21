import { Api } from '/scripts/Api.js';
import { Dialog } from '/scripts/Dialog.js';
import { Strings } from '/scripts/Strings.js';
import { ToggleButton } from '/scripts/ToggleButton.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';

/**
 * Reading one received message in the reader's own language.
 *
 * Nothing here runs on its own. A conversation is not something to hand to a
 * translator on the reader's behalf, so this waits for the button, asks once
 * whether they understand where the words are going, and translates one
 * message at a time. Pressing it again puts the original back.
 */
export class MessageTranslateButton {
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
            language: navigator.language || 'en',
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
