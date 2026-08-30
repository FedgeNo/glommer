import { ClientConfig } from '/scripts/ClientConfig.js';

import Database from '/scripts/vendor/emoji-picker-element/database.js';

const skin_tone = parseInt(ClientConfig.get('currentUserSkinTone'), 10);

if ([0, 1, 2, 3, 4, 5].includes(skin_tone)) {
    try {
        const database = new Database();
        await database.setPreferredSkinTone(skin_tone);
    } catch (error) {
        // A failed preference restore should never keep the picker itself
        // from loading below.
    }
}

await import('/scripts/vendor/emoji-picker-element/index.js');

document.querySelectorAll('.EmojiPicker').forEach(function (wrapper) {
    wrapper.appendChild(document.createElement('emoji-picker'));
});
