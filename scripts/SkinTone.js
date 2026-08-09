import { ClientConfig } from '/scripts/ClientConfig.js';

/**
 * Client twin of SkinTone.php: the reader's chosen skin tone, applied to an
 * emoji this site shows them.
 *
 * A card can be rendered by either side, so the same thumb has to come out of
 * both - the tone travels to the browser on the config cookie as
 * currentUserSkinTone.
 */
export class SkinTone {
    /** The Fitzpatrick modifiers, by the scale the emoji picker reports. */
    static MODIFIERS = {
        1: 0x1f3fb,
        2: 0x1f3fc,
        3: 0x1f3fd,
        4: 0x1f3fe,
        5: 0x1f3ff,
    };

    /** Turns an emoji "text" presentation into its "emoji" one. */
    static VARIATION_SELECTOR = String.fromCodePoint(0xfe0f);

    /**
     * The emoji as this reader should see it. Unchanged where they have chosen
     * nothing, chosen the default, or the emoji is not one that takes a tone.
     */
    static applied(emoji, tone) {
        const modifier = SkinTone.MODIFIERS[parseInt(tone, 10)];

        if (!modifier) return emoji;

        // A modifier replaces the variation selector rather than following it -
        // see SkinTone.php.
        const base = emoji.endsWith(SkinTone.VARIATION_SELECTOR)
            ? emoji.slice(0, -SkinTone.VARIATION_SELECTOR.length)
            : emoji;

        return base + String.fromCodePoint(modifier);
    }

    /** What the reader has chosen, as the config cookie reports it. */
    static forViewer() {
        return ClientConfig.get('currentUserSkinTone');
    }
}
