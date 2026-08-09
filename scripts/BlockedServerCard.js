import { Api } from '/scripts/Api.js';
import { ClientConfig } from '/scripts/ClientConfig.js';
import { Dialog } from '/scripts/Dialog.js';
import { DOMUtils } from '/scripts/DOMUtils.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Toast } from '/scripts/Toast.js';
import { list_in, list_item } from '/scripts/utils.js';
import { Working } from '/scripts/Working.js';

/**
 * The moderation page for shutting out whole servers: the form that adds one
 * and the control on each row that lifts it.
 *
 * Blocking is confirmed rather than immediate, because it is not a small act -
 * it severs every follow in both directions with that server, and lifting the
 * block afterwards does not bring them back.
 */
export class BlockedServerCard {
    static init() {
        const form = document.querySelector('.ServerBlockForm');

        if (form) {
            form.addEventListener('submit', (event) => {
                event.preventDefault();
                BlockedServerCard.#block(form);
            });
        }

        document.addEventListener('click', (event) => {
            const button = event.target.closest('.ServerUnblockButton');

            if (button) {
                BlockedServerCard.#unblock(button);
            }
        });
    }

    static async #block(form) {
        const domain = form.querySelector('[name="domain"]').value.trim();
        const reason = form.querySelector('[name="reason"]').value.trim();

        if (domain === '') return;

        const confirmed = await Dialog.confirm(
            `Block ${domain}? Nothing will be sent to or accepted from that server or anything under it, and existing follows in both directions will be dropped. Unblocking later does not restore them.`
        );

        if (!confirmed) return;

        const submit = form.querySelector('button[type="submit"]');
        Working.start(submit);

        try {
            const result = await Api.post('/api/block-server', { domain, reason });

            if (!result) return;

            // The new row joins the list in place - the same card the server
            // renders for one. The cascade the confirmation warned about
            // (severed follows, dropped deliveries) has no rendering on this
            // page, so the list is the whole picture here.
            const list = list_in(document.querySelector('.BlockedServersSetting'), 'BlockedServerList d-flex flex-column');

            if (list) {
                list.prepend(list_item(BlockedServerCard.#card(result.domain, reason)));
            }

            form.querySelector('[name="domain"]').value = '';
            form.querySelector('[name="reason"]').value = '';
        } finally {
            Working.stop(submit);
        }
    }

    static #card(domain, reason) {
        const card = document.createElement('div');
        card.className = 'BlockedServerCard d-flex align-items-center gap-3';
        card.dataset.domain = domain;

        const info = document.createElement('div');
        info.className = 'd-flex flex-column gap-1';

        const name = document.createElement('p');
        name.textContent = domain;
        info.appendWithSpace(name);

        const detail = document.createElement('p');
        detail.className = 'muted';
        detail.appendWithSpace(document.createTextNode('Blocked by ' + (ClientConfig.get('currentUserUsername') || 'you') + ' '));

        const time = document.createElement('time');
        time.className = 'RelativeTime';
        time.dateTime = new Date().toISOString();
        time.textContent = 'just now';
        detail.appendWithSpace(time);

        if (reason !== '') {
            detail.appendWithSpace(document.createTextNode(' - ' + reason));
        }

        info.appendWithSpace(detail);
        card.appendWithSpace(info);

        const unblock = document.createElement('button');
        unblock.type = 'button';
        unblock.className = 'Button ServerUnblockButton ms-auto';
        unblock.dataset.domain = domain;
        unblock.textContent = 'Unblock';
        card.appendWithSpace(unblock);

        return card;
    }

    static async #unblock(button) {
        const domain = button.dataset.domain;

        if (!await Dialog.confirm(`Unblock ${domain}? Follows that were dropped are not restored - both sides would have to follow again.`)) {
            return;
        }

        Working.start(button);

        try {
            const result = await Api.post('/api/unblock-server', { domain });

            if (!result) return;

            Toast.show(`${domain} unblocked.`);
            DOMUtils.slideOut(button.closest('.BlockedServerCard'));
        } finally {
            Working.stop(button);
        }
    }
}

ReadyHandler.add(BlockedServerCard.init);
