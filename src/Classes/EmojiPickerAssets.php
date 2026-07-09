<?php

declare(strict_types=1);

class EmojiPickerAssets
{
    private const VERSION = '1.22.8';

    /**
     * emoji-picker-element defines a <emoji-picker> custom element and renders
     * entirely inside its own Shadow DOM - its internal styles can't leak out
     * onto the page, and the page's own stylesheets can't reach in and affect
     * it, so there's no cascade fight with style.css in either direction.
     *
     * The picker keeps the user's skin tone preference in its own IndexedDB,
     * and reads it the instant the custom element upgrades - which happens
     * synchronously as soon as the picker module is imported. To make the
     * server-stored preference win over whatever's already in IndexedDB, the
     * preference has to be written via the tree-shaken database submodule
     * (which has no custom-element side effect) before the picker module is
     * imported, and the <emoji-picker> elements have to be created afterward
     * rather than present in the page's initial markup.
     */
    public static function initScript(): Script
    {
        $database_url = 'https://cdn.jsdelivr.net/npm/emoji-picker-element@' . self::VERSION . '/database.js';
        $picker_url = 'https://cdn.jsdelivr.net/npm/emoji-picker-element@' . self::VERSION . '/index.js';

        $script = new Script();
        $script -> attributes['type'] = 'module';
        $script -> attributes['nonce'] = SecurityHeaders::nonce();
        $script -> contents[] = '
import Database from ' . json_encode($database_url) . ';

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

await import(' . json_encode($picker_url) . ');

document.querySelectorAll(\'.EmojiPickerPanel\').forEach(function (panel) {
    panel.appendChild(document.createElement(\'emoji-picker\'));
});
';

        return $script;
    }
}
