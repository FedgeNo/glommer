<?php

declare(strict_types=1);

class EmojiPickerAssets
{
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
     * rather than present in the page's initial markup - see emoji-picker-init.js.
     * A module script (inline or external) is deferred regardless of where it
     * sits in the document, so this can safely load from <head> despite
     * depending on window.currentUserSkinTone, which JSGlobals sets later in
     * the body - it still runs after the whole document has parsed.
     */
    public static function initScript(): Script
    {
        $script = new Script();
        $script -> attributes['type'] = 'module';
        $script -> src = ServerURL::absolute('/emoji-picker-init.js');

        return $script;
    }
}
