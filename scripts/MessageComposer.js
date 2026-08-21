import { Strings } from '/scripts/Strings.js';
import { Api } from '/scripts/Api.js';
import { Dialog } from '/scripts/Dialog.js';
import { Message } from '/scripts/Message.js';
import { MessageCrypto } from '/scripts/MessageCrypto.js';
import { Toast } from '/scripts/Toast.js';
import { list_in, list_item } from '/scripts/utils.js';
import { EmojiPicker } from '/scripts/EmojiPicker.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Working } from '/scripts/Working.js';

export class MessageComposer {
    /**
     * Keeps the page's bottom padding equal to however tall the composer
     * actually is.
     *
     * It is fixed to the bottom of the window, so nothing in the flow knows it
     * is there and the page has to leave the room by hand. A fixed figure only
     * holds while the composer is one fixed size, and it is not: the video
     * call button appears when the other person turns up and adds a row, and
     * the last messages in the thread end up underneath it.
     *
     * Measured rather than counted up from what is in there, so anything else
     * that ever grows it - a wrapped chip, a taller textarea - is covered
     * without this needing to know about it.
     */
    static #reserveRoomFor(composer) {
        const measure = () => {
            document.body.style.setProperty('--composer-height', composer.offsetHeight + 'px');
        };

        // Once up front, so the room is right whether or not anything below
        // this is available to watch for changes.
        measure();

        if (typeof ResizeObserver !== 'undefined') {
            new ResizeObserver(measure).observe(composer);
        }
    }

    static init() {
        // --- Emoji picker (identical to Composer's wiring) ---
        const messageForm = document.querySelector('.MessageComposer');
        if (messageForm) {
            MessageComposer.#reserveRoomFor(messageForm);

            const emojiWrapper = messageForm.querySelector('.EmojiPicker');
            if (emojiWrapper) {
                EmojiPicker.setup(emojiWrapper);
            }
        }

        // --- The privacy chip pops its full explanation ---
        document.addEventListener('click', (event) => {
            const chip = event.target.closest('.MessagePrivacyButton');
            if (!chip) return;
            Dialog.alert(chip.dataset.privacyExplanation);
        });

        // --- Click outside closes the panel ---
        document.addEventListener('click', (event) => {
            if (event.target.closest('.EmojiPickerTriggerButton')) return;
            if (event.target.closest('emoji-picker')) return;
            document.querySelectorAll('emoji-picker.Active').forEach(panel => panel.classList.remove('Active'));
        });

        // --- Submit on Enter (without Shift) ---
        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' || event.shiftKey) return;
            const textarea = event.target.closest('.MessageComposer textarea');
            if (!textarea) return;
            event.preventDefault();
            textarea.closest('form').requestSubmit();
        });

        // --- AJAX submit ---
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.MessageComposer');
            if (!form) return;
            event.preventDefault();

            const submit_button = form.querySelector('button[type="submit"]');
            const body_input = form.querySelector('[name="body"]');
            const recipient_id = form.querySelector('[name="recipientId"]').value;

            // In an encrypted conversation every message is encrypted - a
            // locked thread prompts for the passphrase rather than quietly
            // falling back to plaintext. The unlock form is only rendered for
            // a conversation both sides hold keys for, so its presence is what
            // says this thread is encrypted.
            const payload = { recipientId: recipient_id };
            const unlock_form = document.querySelector('.MessageUnlockForm');

            if (unlock_form !== null) {
                if (MessageCrypto.threadKey() === null) {
                    Toast.show(Strings.for('ClientStatus').unlockConversation || '');
                    document.querySelector('.MessageUnlockForm [name="messagePassphrase"]')?.focus();
                    return;
                }

                if (body_input.value.trim() === '') return;

                payload.envelope = await MessageCrypto.encrypt(MessageCrypto.threadKey(), body_input.value);
            } else {
                payload.body = body_input.value;
            }

            Working.start(submit_button);

            try {
                const result = await Api.post('/api/send-message', payload, { form });

                if (result === null) return;

                const list = list_in(document.querySelector('main'), 'MessageList');

                const message = Message.fromData(result);
                const element = message.toElement();
                list.appendWithSpace(list_item(element));

                body_input.value = '';
                window.scrollTo({ top: document.body.scrollHeight, left: 0, behavior: 'instant' });
            } finally {
                Working.stop(submit_button);
            }
        });
    }
}

ReadyHandler.add(MessageComposer.init);
