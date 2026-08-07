// StagedPostCard.js
import { Api } from '/scripts/Api.js';
import { Dialog } from '/scripts/Dialog.js';
import { DOMUtils } from '/scripts/DOMUtils.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Toast } from '/scripts/Toast.js';

/**
 * The two controls on a draft or scheduled post: publish it now, or discard
 * it for good. Either way the card leaves the page - there is nothing else
 * to keep it there for.
 */
export class StagedPostCard {
    static init() {
        document.addEventListener('click', async (event) => {
            const publish = event.target.closest('.StagedPostPublishButton');
            if (publish) {
                await StagedPostCard.#act(publish, '/api/publish-staged', 'Published.');
                return;
            }

            const discard = event.target.closest('.StagedPostDiscardButton');
            if (discard) {
                if (!await Dialog.confirm('Discard this? It was never published, and this does not keep a copy.')) {
                    return;
                }

                await StagedPostCard.#act(discard, '/api/discard-staged', 'Discarded.');
            }
        });
    }

    static async #act(button, endpoint, done) {
        const card = button.closest('.StagedPostCard');
        button.disabled = true;

        try {
            const result = await Api.post(endpoint, {
                stagedPostId: Number(card.dataset.stagedPostId),
            });

            if (!result) return;

            Toast.show(done);
            DOMUtils.slideOut(card);
        } finally {
            button.disabled = false;
        }
    }
}

ReadyHandler.add(StagedPostCard.init);
