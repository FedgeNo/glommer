import { Strings } from '/scripts/Strings.js';
import { Api } from '/scripts/Api.js';
import { Dialog } from '/scripts/Dialog.js';
import { DOMUtils } from '/scripts/DOMUtils.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Working } from '/scripts/Working.js';

export class EntityModerator {
    static init() {
        document.addEventListener('click', async (event) => {
            const banBtn = event.target.closest('.TrendingEntityBanButton');
            if (banBtn) {
                EntityModerator.#ban(banBtn);
                return;
            }

            const unbanBtn = event.target.closest('.TrendingEntityUnbanButton');
            if (unbanBtn) {
                EntityModerator.#unban(unbanBtn);
            }
        });
    }

    static async #ban(button) {
        const entityType = button.dataset.entityType;
        const entityValue = button.dataset.entityValue;
        const reason = await Dialog.prompt(
            `Ban "${entityValue}" from trending? It won't be able to trend again until unbanned.`,
            {
                confirmLabel: Strings.for('EntityModerator').ban || '',
                placeholder: Strings.for('EntityModerator').banPlaceholder || '',
            }
        );
        if (reason === null) return;
        Working.start(button);
        try {
            const result = await Api.post('/api/ban-trending-entity', { entityType, entityValue, reason });
            if (!result) return;
            DOMUtils.slideOut(button.closest('.Entity'));
        } finally {
            Working.stop(button);
        }
    }

    static async #unban(button) {
        const entityType = button.dataset.entityType;
        const entityValue = button.dataset.entityValue;
        const message = (Strings.for('EntityModerator').unban || '').replace('{entity}', entityValue);
        if (!await Dialog.confirm(message)) return;
        Working.start(button);
        try {
            const result = await Api.post('/api/unban-trending-entity', { entityType, entityValue });
            if (!result) return;
            DOMUtils.slideOut(button.closest('.BannedTrendingEntity'));
        } finally {
            Working.stop(button);
        }
    }
}

ReadyHandler.add(EntityModerator.init);
