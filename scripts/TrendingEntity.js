import { Api } from '/scripts/Api.js';
import { Dialog } from '/scripts/Dialog.js';
import { DOMUtils } from '/scripts/DOMUtils.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';

export class TrendingEntity {
    static init() {
        document.addEventListener('click', async (event) => {
            const banBtn = event.target.closest('.BanTrendingEntityButton');
            if (banBtn) {
                TrendingEntity.#ban(banBtn);
                return;
            }

            const unbanBtn = event.target.closest('.UnbanTrendingEntityButton');
            if (unbanBtn) {
                TrendingEntity.#unban(unbanBtn);
            }
        });
    }

    static async #ban(button) {
        const entityType = button.dataset.entityType;
        const entityValue = button.dataset.entityValue;
        const reason = await Dialog.prompt(
            `Ban "${entityValue}" from trending? It won't be able to trend again until unbanned.`,
            { confirmLabel: 'Ban', placeholder: 'Reason for ban (required)' }
        );
        if (reason === null) return;
        button.disabled = true;
        try {
            const result = await Api.post('/api/ban-trending-entity', { entityType, entityValue, reason });
            if (!result) return;
            DOMUtils.slideOut(button.closest('.TrendingEntityChip'));
        } finally {
            button.disabled = false;
        }
    }

    static async #unban(button) {
        const entityType = button.dataset.entityType;
        const entityValue = button.dataset.entityValue;
        if (!await Dialog.confirm(`Unban "${entityValue}"? It will be able to trend again.`)) return;
        button.disabled = true;
        try {
            const result = await Api.post('/api/unban-trending-entity', { entityType, entityValue });
            if (!result) return;
            DOMUtils.slideOut(button.closest('.BannedTrendingEntity'));
        } finally {
            button.disabled = false;
        }
    }
}

ReadyHandler.add(TrendingEntity.init);
