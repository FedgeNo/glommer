import { Api } from '/scripts/Api.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';

/**
 * Passing a post on. The button carries its own state and count, so it flips in
 * place - the feed itself is not rebuilt, because reposting reorders what is
 * below and moving the page under the reader is worse than a stale ordering
 * they will see corrected on the next load.
 */
export class PostRepostButton {
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

        button.disabled = true;

        try {
            const result = await Api.post('/api/repost', { postId: Number(postId) });

            if (!result) return;

            button.dataset.reposted = result.reposted ? '1' : '0';
            button.textContent = result.count > 0 ? `Repost ${result.count}` : 'Repost';
        } finally {
            button.disabled = false;
        }
    }
}

ReadyHandler.add(PostRepostButton.init);
