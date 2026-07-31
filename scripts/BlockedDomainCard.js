import { Api } from '/scripts/Api.js';
import { Dialog } from '/scripts/Dialog.js';
import { DOMUtils } from '/scripts/DOMUtils.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Toast } from '/scripts/Toast.js';

/**
 * The moderation page for shutting out whole servers: the form that adds one
 * and the control on each row that lifts it.
 *
 * Blocking is confirmed rather than immediate, because it is not a small act -
 * it severs every follow in both directions with that server, and lifting the
 * block afterwards does not bring them back.
 */
export class BlockedDomainCard {
    static init() {
        const form = document.querySelector('.DomainBlockForm');

        if (form) {
            form.addEventListener('submit', (event) => {
                event.preventDefault();
                BlockedDomainCard.#block(form);
            });
        }

        document.addEventListener('click', (event) => {
            const button = event.target.closest('.DomainUnblockButton');

            if (button) {
                BlockedDomainCard.#unblock(button);
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
        submit.disabled = true;

        try {
            const result = await Api.post('/api/block-domain', { domain, reason });

            if (!result) return;

            // Reloaded rather than prepended: the block may have severed
            // follows and dropped queued deliveries, so what else the page is
            // showing has changed too.
            window.location.reload();
        } finally {
            submit.disabled = false;
        }
    }

    static async #unblock(button) {
        const domain = button.dataset.domain;

        if (!await Dialog.confirm(`Unblock ${domain}? Follows that were dropped are not restored - both sides would have to follow again.`)) {
            return;
        }

        button.disabled = true;

        try {
            const result = await Api.post('/api/unblock-domain', { domain });

            if (!result) return;

            Toast.show(`${domain} unblocked.`);
            DOMUtils.slideOut(button.closest('.BlockedDomainCard'));
        } finally {
            button.disabled = false;
        }
    }
}

ReadyHandler.add(BlockedDomainCard.init);
