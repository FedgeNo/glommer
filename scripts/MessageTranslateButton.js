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

            if (body.dataset.originalText !== undefined) {
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

        const words = Strings.for('MessageTranslationNotice', {
            body: 'To translate a message, its text is sent to this server and translated here. Nothing is kept, but a message translated this way has been read by the server, so it is not end-to-end encrypted the way an untranslated message is.',
        });

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

        body.dataset.originalText = original;
        body.textContent = String(result.body);
        body.classList.add('MachineTranslation');
        ToggleButton.select(button, MessageTranslateButton.SHOW_ORIGINAL);
    }

    static #restore(button, body) {
        body.textContent = body.dataset.originalText;
        delete body.dataset.originalText;
        body.classList.remove('MachineTranslation');
        ToggleButton.select(button, MessageTranslateButton.TRANSLATE);
    }
}

ReadyHandler.add(MessageTranslateButton.init);
