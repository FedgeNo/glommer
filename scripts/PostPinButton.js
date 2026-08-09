import { Api } from '/scripts/Api.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Toast } from '/scripts/Toast.js';
import { ToggleButton } from '/scripts/ToggleButton.js';
import { Working } from '/scripts/Working.js';

/**
 * Pinning one of your own posts to the top of your profile.
 *
 * The button carries its own state, so it flips in place rather than needing
 * the page rebuilt. The profile itself is only rebuilt on the next load - the
 * pinned section lives above the feed and reordering it live would move
 * whatever the reader is looking at.
 */
export class PostPinButton {
    static init() {
        document.addEventListener('click', (event) => {
            const button = event.target.closest('.PostPinButton');

            if (button) {
                PostPinButton.#toggle(button);
            }
        });
    }

    static async #toggle(button) {
        const post = button.closest('.Post');
        const postId = post?.dataset.postId;

        if (!postId) return;

        Working.start(button);

        try {
            const result = await Api.post('/api/pin-post', { postId: Number(postId) });

            if (!result) return;

            button.setAttribute('aria-pressed', result.pinned ? 'true' : 'false');
            button.setAttribute('aria-label', result.pinned ? 'Unpin' : 'Pin');
            button.setAttribute('title', result.pinned ? 'Unpin' : 'Pin');
            button.classList.toggle('Removing', result.pinned);
            Toast.show(result.pinned ? 'Pinned to your profile.' : 'Unpinned.');
        } finally {
            Working.stop(button);
        }
    }
}

ReadyHandler.add(PostPinButton.init);
