import { Api } from '/scripts/Api.js';

let activePanel = null;
let activeWrapper = null;

// Global click‑outside listener – closes the panel when clicking elsewhere
document.addEventListener('click', (event) => {
    if (!activePanel) return;
    if (event.target.closest('.EmojiPickerTriggerButton')) return;
    if (event.target.closest('emoji-picker')) return;
    closeActive();
});

function closeActive() {
    if (activePanel && activeWrapper) {
        activePanel.classList.remove('Active');
        // Move panel back to its original wrapper
        activeWrapper.appendChild(activePanel);
        activePanel = null;
        activeWrapper = null;
    }
}

export class EmojiPicker {
    static initAll(root = document) {
        root.querySelectorAll('.EmojiPicker').forEach(btn => EmojiPicker.setup(btn));
    }

    static setup(wrapper) {
        const trigger = wrapper.querySelector('.EmojiPickerTriggerButton');
        const panel  = wrapper.querySelector('emoji-picker');
        if (!trigger || !panel) return;

        // Replace trigger to remove any previous event listeners
        const newTrigger = trigger.cloneNode(true);
        trigger.replaceWith(newTrigger);

        newTrigger.addEventListener('click', (e) => {
            e.stopPropagation();

            // If this panel is already active, close it
            if (panel === activePanel) {
                closeActive();
                return;
            }

            // Close any other open panel
            closeActive();

            // Move the panel to document.body so fixed positioning is relative
            // to the viewport (avoids issues when the button is inside a fixed/transformed container)
            document.body.appendChild(panel);

            // Shown before it is placed, so it can be measured rather than
            // assumed: the picker decides its own size, and a number written
            // down here would only ever be the wrong one by a few pixels.
            // Hidden across the measurement so it is never painted at the
            // default position first.
            panel.style.visibility = 'hidden';
            panel.style.position = 'fixed';
            panel.style.top = '0';
            panel.style.left = '0';
            panel.classList.add('Active');

            const triggerRect = newTrigger.getBoundingClientRect();
            const panelRect = panel.getBoundingClientRect();
            const gap = 4;

            // Vertical: prefer below the trigger
            let top = triggerRect.bottom + gap;
            if (top + panelRect.height > window.innerHeight) {
                top = triggerRect.top - panelRect.height - gap;
            }
            if (top < gap) top = gap;

            // Horizontal: align left edges, keep inside viewport
            let left = triggerRect.left;
            if (left + panelRect.width > window.innerWidth) {
                left = window.innerWidth - panelRect.width - gap;
            }
            if (left < gap) left = gap;

            panel.style.top  = top + 'px';
            panel.style.left = left + 'px';
            panel.style.visibility = '';

            activePanel = panel;
            activeWrapper = wrapper;
        });

        // Emoji insertion
        panel.addEventListener('emoji-click', (event) => {
            const emoji = event.detail.unicode;
            const form = wrapper.closest('form');
            const quill = form?.querySelector('.QuillEditor')?.__quill;
            if (quill) {
                const selection = quill.getSelection(true);
                quill.insertText(selection.index, emoji, 'user');
                quill.setSelection(selection.index + emoji.length, 0, 'user');
                return;
            }
            const textarea = form?.querySelector('textarea');
            if (textarea) {
                const start = textarea.selectionStart;
                const end = textarea.selectionEnd;
                const value = textarea.value;
                textarea.value = value.slice(0, start) + emoji + value.slice(end);
                textarea.selectionStart = textarea.selectionEnd = start + emoji.length;
                textarea.focus();
            }
        });

        // Skin tone preference
        panel.addEventListener('skin-tone-change', async (event) => {
            await Api.post('/api/update-skin-tone', { skinTone: String(event.detail.skinTone) });
        });
    }
}

