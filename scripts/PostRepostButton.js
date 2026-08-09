import { Api } from '/scripts/Api.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { ToggleButton } from '/scripts/ToggleButton.js';
import { Working } from '/scripts/Working.js';

/**
 * Passing a post on. The button carries its own state and count, so it flips in
 * place - the feed itself is not rebuilt, because reposting reorders what is
 * below and moving the page under the reader is worse than a stale ordering
 * they will see corrected on the next load.
 */
export class PostRepostButton {
    /** Mirrors PostRepostButton::label() - the two must agree or the button rewords itself when pressed. */
    /** The arrows, with the count beside them once there is one. Mirrors PostRepostButton.php. */
    static GLYPH = '🔁';

    static label(reposted, count) {
        return count > 0 ? PostRepostButton.GLYPH + ' ' + count : PostRepostButton.GLYPH;
    }

    static init() {
        document.addEventListener('click', (event) => {
            const button = event.target.closest('.PostRepostButton');

            if (button) {
                PostRepostButton.#toggle(button);
            }
        });
    }

    static async #toggle(button) {
        const postId = button.closest('.Post')?.dataset.postId;

        if (!postId) return;

        Working.start(button);

        try {
            const result = await Api.post('/api/repost', { postId: Number(postId) });

            if (!result) return;

            button.classList.toggle('Removing', result.reposted);
            ToggleButton.setLabel(button, PostRepostButton.label(result.reposted, result.count));
            button.setAttribute('aria-pressed', result.reposted ? 'true' : 'false');
            button.setAttribute('aria-label', result.reposted ? 'Undo repost' : 'Repost');
            button.setAttribute('title', result.reposted ? 'Undo repost' : 'Repost');
        } finally {
            Working.stop(button);
        }
    }
}

ReadyHandler.add(PostRepostButton.init);
