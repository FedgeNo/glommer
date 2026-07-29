import { Api } from '/scripts/Api.js';

let activePanel = null;
let activeWrapper = null;

// Global click‑outside listener – closes the panel when clicking elsewhere
document.addEventListener('click', (event) => {
    if (!activePanel) return;
    if (event.target.closest('.EmojiTriggerButton')) return;
    if (event.target.closest('.EmojiPickerPanel')) return;
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
        root.querySelectorAll('.EmojiPickerButton').forEach(btn => EmojiPicker.setup(btn));
    }

    static setup(wrapper) {
        const trigger = wrapper.querySelector('.EmojiTriggerButton');
        const panel  = wrapper.querySelector('.EmojiPickerPanel');
        if (!trigger || !panel) return;

        // Hard dimensions – no measurement needed
        panel.style.width  = '352px';
        panel.style.height = '430px';

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

            const triggerRect = newTrigger.getBoundingClientRect();
            const gap = 4;
            const panelWidth  = 352;
            const panelHeight = Math.min(430, window.innerHeight * 0.7);

            // Vertical: prefer below the trigger
            let top = triggerRect.bottom + gap;
            if (top + panelHeight > window.innerHeight) {
                top = triggerRect.top - panelHeight - gap;
            }
            if (top < gap) top = gap;

            // Horizontal: align left edges, keep inside viewport
            let left = triggerRect.left;
            if (left + panelWidth > window.innerWidth) {
                left = window.innerWidth - panelWidth - gap;
            }
            if (left < gap) left = gap;

            panel.style.position = 'fixed';
            panel.style.top  = top + 'px';
            panel.style.left = left + 'px';
            panel.classList.add('Active');

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

