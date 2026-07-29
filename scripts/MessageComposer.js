import { Api } from '/scripts/Api.js';
import { Message } from '/scripts/Message.js';
import { list_item } from '/scripts/utils.js';
import { EmojiPicker } from '/scripts/EmojiPicker.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';

export class MessageComposer {
    static init() {
        // --- Emoji picker (identical to Composer's wiring) ---
        const messageForm = document.querySelector('.MessageComposer');
        if (messageForm) {
            const emojiWrapper = messageForm.querySelector('.EmojiPickerButton');
            if (emojiWrapper) {
                EmojiPicker.setup(emojiWrapper);
            }
        }

        // --- Click outside closes the panel ---
        document.addEventListener('click', (event) => {
            if (event.target.closest('.EmojiTriggerButton')) return;
            if (event.target.closest('.EmojiPickerPanel')) return;
            document.querySelectorAll('.EmojiPickerPanel.Active').forEach(panel => panel.classList.remove('Active'));
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

            submit_button.disabled = true;

            try {
                const result = await Api.post('/api/send-message', {
                    recipientId: recipient_id,
                    body: body_input.value,
                });

                if (result === null) return;

                const list = document.querySelector('.MessageList');
                const placeholder = list.querySelector('.Notice');
                if (placeholder) placeholder.closest('li').remove();

                const message = Message.fromData(result);
                const element = message.toElement();
                list.appendWithSpace(list_item(element));

                body_input.value = '';
                window.scrollTo({ top: document.body.scrollHeight, left: 0, behavior: 'instant' });
            } finally {
                submit_button.disabled = false;
            }
        });
    }
}

ReadyHandler.add(MessageComposer.init);
