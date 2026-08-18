// StagedPostCard.js
import { Api } from '/scripts/Api.js';
import { Dialog } from '/scripts/Dialog.js';
import { DOMUtils } from '/scripts/DOMUtils.js';
import { ClientConfig } from '/scripts/ClientConfig.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Toast } from '/scripts/Toast.js';
import { Working } from '/scripts/Working.js';

/**
 * The controls on a draft or scheduled post: publish it now, open it for
 * editing, or discard it for good. Publishing and discarding take the card
 * with them; editing is a link to the composer holding it, a page of its own
 * built from what the server saved.
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

    /** Mirrors StagedPostCard.php, rebuilt from what the server saved. */
    static #card(data) {
        const card = document.createElement('div');
        card.className = 'Card StagedPostCard';
        card.setAttribute('data-staged-post-id', data.stagedPostId);
        card.setAttribute('data-title', data.title || '');
        card.setAttribute('data-description-delta', data.descriptionDelta || '');
        card.setAttribute('data-link-url', data.linkURL || '');

        if (data.publishAtEpoch) {
            card.setAttribute('data-publish-at-epoch', data.publishAtEpoch);
        }

        if (data.latitude !== null && data.latitude !== undefined) {
            card.setAttribute('data-latitude', data.latitude);
            card.setAttribute('data-longitude', data.longitude);
        }

        if (data.title) {
            const title = document.createElement('p');
            title.className = 'StagedPostTitle';
            title.textContent = data.title;
            card.appendWithSpace(title);
        }

        if (data.description) {
            const body = document.createElement('p');
            body.textContent = data.description.length > 200 ? data.description.slice(0, 200) + '…' : data.description;
            card.appendWithSpace(body);
        }

        if (data.linkURL) {
            const link = document.createElement('p');
        link.className = 'StagedPostLink';
            link.textContent = data.linkURL;
            card.appendWithSpace(link);
        }

        const when_words = Strings.for('StagedPostWhen', {
            scheduled: 'Scheduled for {when}',
            draft: 'Draft - publishes only when you say so',
        });

        const when = document.createElement('p');
        when.className = 'StagedPostWhen';
        when.textContent = data.publishAt !== null
            ? when_words.scheduled.replace('{when}', data.publishAt)
            : when_words.draft;
        card.appendWithSpace(when);

        const actions = document.createElement('div');
        actions.className = 'StagedPostActions';

        for (const [className, label] of [
            ['StagedPostPublishButton', Strings.for('StagedPostPublishButton', { name: 'Publish Now' }).name],
            ['StagedPostDiscardButton Removing', Strings.for('StagedPostDiscardButton', { name: 'Discard' }).name],
        ]) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'Button ' + className;
            button.textContent = label;
            actions.appendWithSpace(button);
        }

        // A link, mirroring StagedPostEditButton.php: editing is a page of its
        // own, holding the composer this was written in.
        const edit = document.createElement('a');
        edit.className = 'Button StagedPostEditButton';
        edit.href = ClientConfig.siteURL() + '/drafts/' + data.stagedPostId;
        edit.textContent = 'Edit';
        actions.appendWithSpace(edit);

        card.appendWithSpace(actions);

        return card;
    }

    static async #act(button, endpoint, done) {
        const card = button.closest('.StagedPostCard');
        Working.start(button);

        try {
            const result = await Api.post(endpoint, {
                stagedPostId: Number(card.dataset.stagedPostId),
            });

            if (!result) return;

            Toast.show(done);
            DOMUtils.slideOut(card);
        } finally {
            Working.stop(button);
        }
    }
}

ReadyHandler.add(StagedPostCard.init);
