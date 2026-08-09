import { Api } from '/scripts/Api.js';
import { Dialog } from '/scripts/Dialog.js';
import { DOMUtils } from '/scripts/DOMUtils.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { list_in, list_item } from '/scripts/utils.js';
import { Working } from '/scripts/Working.js';

/**
 * The Relays page: the form that joins one and the control on each row that
 * withdraws.
 *
 * Both are confirmed rather than immediate. Joining commits this server to
 * carrying whatever the other side publishes, and leaving stops a stream that
 * people may have been reading - neither is a small act, and neither is
 * obvious from a button.
 */
export class RelayCard {
    static init() {
        document.addEventListener('submit', (event) => {
            const form = event.target.closest('.RelaySubscribeForm');
            if (!form) return;
            event.preventDefault();
            RelayCard.#subscribe(form);
        });

        document.addEventListener('click', (event) => {
            const button = event.target.closest('.RelayUnsubscribeButton');
            if (!button) return;
            RelayCard.#unsubscribe(button);
        });
    }

    static async #subscribe(form) {
        const actor_uri = form.querySelector('[name="actorURI"]').value.trim();
        const follow_object = form.querySelector('[name="followObject"]').value;

        if (actor_uri === '') return;

        const confirmed = await Dialog.confirm(
            `Subscribe to ${actor_uri}? Every public post from every other server subscribed to it will arrive here, and this server's will go out to all of them. How much that is depends entirely on those servers.`
        );

        if (!confirmed) return;

        const submit = form.querySelector('button[type="submit"]');
        Working.start(submit);

        try {
            const result = await Api.post('/api/subscribe-relay', { actorURI: actor_uri, followObject: follow_object });

            if (!result) return;

            const list = list_in(document.querySelector('.RelaysSetting'), 'RelayList d-flex flex-column');

            if (list) {
                list.prepend(list_item(RelayCard.#card(result.actorURI)));
            }

            form.querySelector('[name="actorURI"]').value = '';
        } finally {
            Working.stop(submit);
        }
    }

    static async #unsubscribe(button) {
        const actor_uri = button.dataset.actorUri;

        if (!await Dialog.confirm(`Unsubscribe from ${actor_uri}? Nothing new will arrive from it. Posts it already brought stay where they are.`)) {
            return;
        }

        Working.start(button);

        try {
            const result = await Api.post('/api/unsubscribe-relay', { actorURI: actor_uri });

            if (!result) return;

            DOMUtils.slideOut(button.closest('.RelayCard'));
        } finally {
            Working.stop(button);
        }
    }

    /** The row the server renders for a subscription that has yet to be answered. */
    static #card(actor_uri) {
        const card = document.createElement('div');
        card.className = 'RelayCard d-flex align-items-center gap-3';
        card.dataset.actorUri = actor_uri;

        const info = document.createElement('div');
        info.className = 'd-flex flex-column gap-1';

        const name = document.createElement('p');
        name.textContent = actor_uri;
        info.appendWithSpace(name);

        const detail = document.createElement('p');
        detail.className = 'muted';
        detail.appendWithSpace(document.createTextNode('Waiting for the relay to accept - subscribed '));

        const time = document.createElement('time');
        time.className = 'RelativeTime';
        time.dateTime = new Date().toISOString();
        time.textContent = 'just now';
        detail.appendWithSpace(time);

        info.appendWithSpace(detail);
        card.appendWithSpace(info);

        const unsubscribe = document.createElement('button');
        unsubscribe.type = 'button';
        unsubscribe.className = 'Button RelayUnsubscribeButton ms-auto';
        unsubscribe.dataset.actorUri = actor_uri;
        unsubscribe.textContent = 'Unsubscribe';
        card.appendWithSpace(unsubscribe);

        return card;
    }
}

ReadyHandler.add(RelayCard.init);
