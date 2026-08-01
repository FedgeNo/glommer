import { Api } from '/scripts/Api.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Toast } from '/scripts/Toast.js';

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

        button.disabled = true;

        try {
            const result = await Api.post('/api/pin-post', { postId: Number(postId) });

            if (!result) return;

            button.textContent = result.pinned ? 'Unpin' : 'Pin';
            Toast.show(result.pinned ? 'Pinned to your profile.' : 'Unpinned.');
        } finally {
            button.disabled = false;
        }
    }
}

ReadyHandler.add(PostPinButton.init);
