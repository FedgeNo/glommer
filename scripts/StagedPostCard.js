// StagedPostCard.js
import { Api } from '/scripts/Api.js';
import { Dialog } from '/scripts/Dialog.js';
import { DOMUtils } from '/scripts/DOMUtils.js';
import { QuillEditor } from '/scripts/QuillEditor.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Toast } from '/scripts/Toast.js';

/**
 * The controls on a draft or scheduled post: publish it now, edit it in
 * place, or discard it for good. Publishing and discarding take the card
 * with them; editing swaps the card for a form and swaps back a fresh card
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

            const edit = event.target.closest('.StagedPostEditButton');
            if (edit) {
                StagedPostCard.#openEditor(edit.closest('.StagedPostCard'));
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

    /**
     * The edit form, standing where the card was: title, body, link, and the
     * schedule as a date with an optional time - clearing the date turns a
     * scheduled post back into a plain draft.
     */
    static #openEditor(card) {
        if (card.nextElementSibling?.classList.contains('StagedPostEditForm')) return;

        const form = document.createElement('form');
        form.className = 'Form StagedPostEditForm d-flex flex-column gap-2';

        const title = document.createElement('input');
        title.type = 'text';
        title.name = 'title';
        title.placeholder = 'Title (optional)';
        title.maxLength = 255;
        title.value = card.dataset.title || '';
        form.appendWithSpace(title);

        const editorContainer = document.createElement('div');
        editorContainer.className = 'QuillEditor';
        form.appendWithSpace(editorContainer);

        const link = document.createElement('input');
        link.type = 'text';
        link.name = 'linkURL';
        link.placeholder = 'Link (optional)';
        link.maxLength = 255;
        link.value = card.dataset.linkUrl || '';
        form.appendWithSpace(link);

        const scheduleRow = document.createElement('div');
        scheduleRow.className = 'd-flex gap-2 align-items-center';

        const date = document.createElement('input');
        date.type = 'date';
        date.min = new Date().toISOString().slice(0, 10);
        date.setAttribute('aria-label', 'Publish date (blank keeps it a draft)');
        scheduleRow.appendWithSpace(date);

        const time = document.createElement('input');
        time.type = 'time';
        time.setAttribute('aria-label', 'Publish time (optional)');
        scheduleRow.appendWithSpace(time);

        if (card.dataset.publishAtEpoch) {
            const when = new Date(Number(card.dataset.publishAtEpoch) * 1000);
            date.value = when.getFullYear() + '-' + String(when.getMonth() + 1).padStart(2, '0') + '-' + String(when.getDate()).padStart(2, '0');
            time.value = String(when.getHours()).padStart(2, '0') + ':' + String(when.getMinutes()).padStart(2, '0');
        }

        form.appendWithSpace(scheduleRow);

        const actions = document.createElement('div');
        actions.className = 'd-flex gap-2';

        const cancel = document.createElement('button');
        cancel.type = 'button';
        cancel.className = 'Button';
        cancel.textContent = 'Cancel';
        cancel.addEventListener('click', () => {
            card.style.display = '';
            form.remove();
        });
        actions.appendWithSpace(cancel);

        const save = document.createElement('button');
        save.type = 'submit';
        save.className = 'Button';
        save.textContent = 'Save';
        actions.appendWithSpace(save);

        form.appendWithSpace(actions);

        card.style.display = 'none';
        card.insertAdjacentElement('afterend', form);

        const editor = new QuillEditor(editorContainer, { placeholder: 'What\'s on your mind?' });

        try {
            if (card.dataset.descriptionDelta) {
                editor.instance.setContents(JSON.parse(card.dataset.descriptionDelta));
            }
        } catch (_) {}

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            save.disabled = true;

            try {
                const epoch = date.value !== ''
                    ? Math.floor(new Date(date.value + 'T' + (time.value || '00:00')).getTime() / 1000)
                    : null;

                const result = await Api.post('/api/update-staged', {
                    stagedPostId: Number(card.dataset.stagedPostId),
                    title: title.value,
                    description: JSON.stringify(editor.instance.getContents()),
                    linkURL: link.value,
                    // The location isn't editable here, but it must survive
                    // the save rather than be quietly dropped by it.
                    latitude: card.dataset.latitude ?? '',
                    longitude: card.dataset.longitude ?? '',
                    publishAtEpoch: epoch,
                });

                if (!result) return;

                Toast.show('Saved.');
                form.remove();
                card.replaceWith(StagedPostCard.#card(result));
            } finally {
                save.disabled = false;
            }
        });
    }

    /** Mirrors StagedPostCard.php, rebuilt from what the server saved. */
    static #card(data) {
        const card = document.createElement('div');
        card.className = 'Card StagedPostCard d-flex flex-column gap-2';
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
            link.className = 'muted text-sm';
            link.textContent = data.linkURL;
            card.appendWithSpace(link);
        }

        const when = document.createElement('p');
        when.className = 'StagedPostWhen muted text-sm';
        when.textContent = data.publishAt !== null
            ? 'Scheduled for ' + data.publishAt
            : 'Draft - publishes only when you say so';
        card.appendWithSpace(when);

        const actions = document.createElement('div');
        actions.className = 'd-flex gap-2';

        for (const [className, label] of [
            ['StagedPostPublishButton', 'Publish Now'],
            ['StagedPostEditButton', 'Edit'],
            ['StagedPostDiscardButton Removing', 'Discard'],
        ]) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'Button ' + className;
            button.textContent = label;
            actions.appendWithSpace(button);
        }

        card.appendWithSpace(actions);

        return card;
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
