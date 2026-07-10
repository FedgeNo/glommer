import Database from 'https://cdn.jsdelivr.net/npm/emoji-picker-element@1.22.8/database.js';

const skin_tone = parseInt(window.currentUserSkinTone, 10);

if ([0, 1, 2, 3, 4, 5].includes(skin_tone)) {
    try {
        const database = new Database();
        await database.setPreferredSkinTone(skin_tone);
    } catch (error) {
        // A failed preference restore should never keep the picker itself
        // from loading below.
    }
}

await import('https://cdn.jsdelivr.net/npm/emoji-picker-element@1.22.8/index.js');

document.querySelectorAll('.EmojiPickerPanel').forEach(function (panel) {
    panel.appendChild(document.createElement('emoji-picker'));
});
